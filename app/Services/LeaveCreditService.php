<?php

namespace App\Services;

use App\Models\LeaveCreditTransaction;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveCreditService
{
    public const ANNUAL_CREDITS = 5;

    public const MANUAL_TYPES = ['expired', 'carried_over', 'paid_out', 'adjustment'];

    private array $workdayCache = [];

    public function __construct(private AttendanceScheduleService $attendance) {}

    public function summary(User $employee, CarbonImmutable|string|null $asOf = null): array
    {
        $date = $this->date($asOf);
        $startDate = $this->hireDate($employee);
        if (! $startDate) {
            return $this->emptySummary();
        }

        $this->sync($employee, $date);
        $eligibilityDate = $startDate->addYear();
        $eligible = $date->gte($eligibilityDate);
        $cycleStart = $eligible ? $this->cycleStart($startDate, $date) : $eligibilityDate;
        $cycleEnd = $cycleStart->addYear()->subDay();
        $cycle = $employee->leaveCreditTransactions()->whereDate('cycle_start', $cycleStart)->get();
        $used = abs((float) $cycle->where('type', 'used')->sum('amount'));
        $pending = $this->daysPending($employee, $this->leaves($employee), $cycleStart, $cycleEnd);
        $available = (float) $employee->leaveCreditTransactions()->whereDate('effective_date', '<=', $date)->sum('amount');

        return [
            'hire_date' => $startDate->toDateString(), 'eligibility_date' => $eligibilityDate->toDateString(),
            'eligible' => $eligible, 'annual_credits' => $eligible ? self::ANNUAL_CREDITS : 0,
            'used_credits' => $used, 'pending_days' => $pending, 'available_credits' => max(0, round($available, 2)),
            'cycle_start' => $cycleStart->toDateString(), 'cycle_end' => $cycleEnd->toDateString(),
            'days_until_eligible' => $eligible ? 0 : $date->diffInDays($eligibilityDate),
        ];
    }

    public function ledger(User $employee, CarbonImmutable|string|null $asOf = null): array
    {
        $date = $this->date($asOf);
        $this->sync($employee, $date);
        $running = 0.0;
        $entries = $employee->leaveCreditTransactions()->with('creator:id,name')
            ->whereDate('effective_date', '<=', $date)->orderBy('effective_date')->orderBy('id')->get()
            ->map(function (LeaveCreditTransaction $entry) use (&$running) {
                $running = round($running + (float) $entry->amount, 2);

                return [
                    'id' => $entry->id, 'type' => $entry->type, 'amount' => (float) $entry->amount,
                    'balance' => $running, 'effective_date' => $entry->effective_date->toDateString(),
                    'cycle_start' => $entry->cycle_start->toDateString(), 'cycle_end' => $entry->cycle_end->toDateString(),
                    'reference' => $entry->reference, 'notes' => $entry->notes, 'automatic' => $entry->automatic,
                    'created_by' => $entry->creator?->name,
                ];
            })->reverse()->values();

        return [
            'entries' => $entries,
            'totals' => collect(['earned', 'used', 'expired', 'carried_over', 'paid_out', 'adjustment'])
                ->mapWithKeys(fn ($type) => [$type => round((float) $entries->where('type', $type)->sum('amount'), 2)])->all(),
            'balance' => max(0, round($running, 2)),
        ];
    }

    public function post(User $employee, User $actor, array $data): LeaveCreditTransaction
    {
        $effectiveDate = $this->date($data['effective_date']);
        $hireDate = $this->hireDate($employee);
        abort_unless($hireDate && $effectiveDate->gte($hireDate->addYear()), 422, 'The effective date must be on or after the employee becomes eligible.');
        $cycleStart = $this->cycleStart($hireDate, $effectiveDate);
        $amount = (float) $data['amount'];
        $amount = in_array($data['type'], ['expired', 'paid_out'], true) ? -abs($amount) : ($data['type'] === 'carried_over' ? abs($amount) : $amount);

        return DB::transaction(function () use ($employee, $actor, $data, $effectiveDate, $cycleStart, $amount) {
            $this->sync($employee, $effectiveDate);
            $balance = (float) $employee->leaveCreditTransactions()->whereDate('effective_date', '<=', $effectiveDate)->sum('amount');
            abort_if($amount < 0 && abs($amount) > $balance, 422, 'This transaction exceeds the available leave credit balance.');

            return $employee->leaveCreditTransactions()->create([
                'created_by' => $actor->id, 'type' => $data['type'], 'amount' => $amount,
                'effective_date' => $effectiveDate, 'cycle_start' => $cycleStart, 'cycle_end' => $cycleStart->addYear()->subDay(),
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'],
                'source_key' => 'manual:'.str()->uuid(), 'automatic' => false,
            ]);
        });
    }

    public function sync(User $employee, CarbonImmutable|string|null $asOf = null): void
    {
        $date = $this->date($asOf);
        $hireDate = $this->hireDate($employee);
        if (! $hireDate || $date->lt($hireDate->addYear())) {
            return;
        }

        DB::transaction(function () use ($employee, $date, $hireDate) {
            $usedKeys = [];
            $cycleStart = $hireDate->addYear();
            while ($cycleStart->lte($date)) {
                $cycleEnd = $cycleStart->addYear()->subDay();
                LeaveCreditTransaction::updateOrCreate(
                    ['source_key' => "leave-award:{$employee->id}:{$cycleStart->toDateString()}"],
                    ['user_id' => $employee->id, 'type' => 'earned', 'amount' => self::ANNUAL_CREDITS,
                        'effective_date' => $cycleStart, 'cycle_start' => $cycleStart, 'cycle_end' => $cycleEnd,
                        'reference' => 'Annual service credit', 'notes' => 'Automatically earned after a completed year of service.',
                        'source_type' => 'service_year', 'automatic' => true],
                );

                foreach ($this->leaves($employee)->filter(fn (LeaveRequest $leave) => $leave->status === 'approved' && $leave->isPaid()) as $leave) {
                    $days = $this->overlapDays($employee, $leave, $cycleStart, $cycleEnd);
                    if (! $days) {
                        continue;
                    }
                    $key = "leave-request:{$leave->id}:{$cycleStart->toDateString()}";
                    $usedKeys[] = $key;
                    LeaveCreditTransaction::updateOrCreate(
                        ['source_key' => $key],
                        ['user_id' => $employee->id, 'type' => 'used', 'amount' => -$days,
                            'effective_date' => max($leave->start_date->toDateString(), $cycleStart->toDateString()),
                            'cycle_start' => $cycleStart, 'cycle_end' => $cycleEnd, 'reference' => "Leave request #{$leave->id}",
                            'notes' => $leave->leave_type, 'source_type' => LeaveRequest::class, 'source_id' => $leave->id, 'automatic' => true],
                    );
                }

                if ($cycleEnd->lt($date)) {
                    $key = "leave-expiry:{$employee->id}:{$cycleStart->toDateString()}";
                    $remaining = (float) $employee->leaveCreditTransactions()->whereDate('cycle_start', $cycleStart)->where('source_key', '!=', $key)->sum('amount');
                    LeaveCreditTransaction::updateOrCreate(
                        ['source_key' => $key],
                        ['user_id' => $employee->id, 'type' => 'expired', 'amount' => -max(0, $remaining),
                            'effective_date' => $cycleEnd, 'cycle_start' => $cycleStart, 'cycle_end' => $cycleEnd,
                            'reference' => 'Service-year expiry', 'notes' => 'Unused credits expired when the service year closed.',
                            'source_type' => 'service_year', 'automatic' => true],
                    );
                }
                $cycleStart = $cycleStart->addYear();
            }

            $stale = $employee->leaveCreditTransactions()->where('automatic', true)->where('type', 'used');
            $usedKeys ? $stale->whereNotIn('source_key', $usedKeys)->delete() : $stale->delete();
        });
    }

    public function paidApprovalErrors(User $employee, LeaveRequest $request): array
    {
        $employee->loadMissing(['personalInformation', 'leaveRequests']);
        $startDate = $this->hireDate($employee);
        if (! $startDate) {
            return ['A hire date is required before paid leave can be approved.'];
        }

        $requiredByCycle = [];
        $day = CarbonImmutable::parse($request->start_date->toDateString(), 'Asia/Manila');
        $end = CarbonImmutable::parse($request->end_date->toDateString(), 'Asia/Manila');
        while ($day->lte($end)) {
            if ($this->isWorkday($employee, $day)) {
                if ($day->lt($startDate->addYear())) {
                    return ['Paid leave begins only after the employee completes one year of service.'];
                }
                $cycle = $this->cycleStart($startDate, $day)->toDateString();
                $requiredByCycle[$cycle] = ($requiredByCycle[$cycle] ?? 0) + 1;
            }
            $day = $day->addDay();
        }

        $errors = [];
        $leaves = $this->leaves($employee)->where('id', '!=', $request->id);
        foreach ($requiredByCycle as $cycle => $required) {
            $cycleStart = CarbonImmutable::parse($cycle, 'Asia/Manila');
            $used = $this->daysUsed($employee, $leaves, $cycleStart, $cycleStart->addYear()->subDay());
            $available = max(0, self::ANNUAL_CREDITS - $used);
            if ($required > $available) {
                $errors[] = "Only {$available} paid leave credit(s) remain for the service year beginning {$cycle}.";
            }
        }

        return $errors;
    }

    private function date(CarbonImmutable|string|null $value): CarbonImmutable
    {
        return $value instanceof CarbonImmutable ? $value->startOfDay() : CarbonImmutable::parse($value ?? now('Asia/Manila')->toDateString(), 'Asia/Manila')->startOfDay();
    }

    private function hireDate(User $employee): ?CarbonImmutable
    {
        $employee->loadMissing('personalInformation');

        return $employee->personalInformation?->start_date ? CarbonImmutable::parse($employee->personalInformation->start_date->toDateString(), 'Asia/Manila')->startOfDay() : null;
    }

    private function cycleStart(CarbonImmutable $hireDate, CarbonImmutable $date): CarbonImmutable
    {
        $cycle = $hireDate->addYears((int) $hireDate->diffInYears($date));

        return $cycle->gt($date) ? $cycle->subYear() : $cycle;
    }

    private function leaves(User $employee): Collection
    {
        return $employee->relationLoaded('leaveRequests') ? $employee->leaveRequests : $employee->leaveRequests()->get();
    }

    private function daysUsed(User $employee, Collection $leaves, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $leaves->filter(fn (LeaveRequest $leave) => $leave->status === 'approved' && $leave->isPaid())->sum(fn (LeaveRequest $leave) => $this->overlapDays($employee, $leave, $from, $to));
    }

    private function daysPending(User $employee, Collection $leaves, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $leaves->filter(fn (LeaveRequest $leave) => in_array($leave->status, ['needs_review', 'pending'], true))->sum(fn (LeaveRequest $leave) => $this->overlapDays($employee, $leave, $from, $to));
    }

    private function overlapDays(User $employee, LeaveRequest $leave, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $start = CarbonImmutable::parse($leave->start_date->toDateString(), 'Asia/Manila')->max($from);
        $end = CarbonImmutable::parse($leave->end_date->toDateString(), 'Asia/Manila')->min($to);
        $days = 0;
        while ($start->lte($end)) {
            $days += $this->isWorkday($employee, $start) ? 1 : 0;
            $start = $start->addDay();
        }

        return $days;
    }

    private function isWorkday(User $employee, CarbonImmutable $date): bool
    {
        $key = $employee->id.':'.$date->toDateString();
        if (! array_key_exists($key, $this->workdayCache)) {
            $snapshot = $this->attendance->snapshot($employee, $date->toDateString());
            $this->workdayCache[$key] = ! $snapshot['rest_day'] && isset($snapshot['times']['time_in'], $snapshot['times']['time_out']);
        }

        return $this->workdayCache[$key];
    }

    private function emptySummary(): array
    {
        return ['hire_date' => null, 'eligibility_date' => null, 'eligible' => false, 'annual_credits' => 0, 'used_credits' => 0, 'pending_days' => 0, 'available_credits' => 0, 'cycle_start' => null, 'cycle_end' => null, 'days_until_eligible' => null];
    }
}
