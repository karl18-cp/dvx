<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ThirteenthMonthService
{
    /** @return array{rows: Collection<int, array<string, mixed>>, monthly: array<int, array<string, int|string>>} */
    public function report(int $year, ?Collection $employees = null): array
    {
        $employees ??= User::query()->whereNotNull('username')->where('role', '!=', 'trainee')->get(['id', 'name', 'username', 'role', 'status']);
        $employeeIds = $employees->pluck('id');
        $allEntries = AccountingEntry::query()->whereIn('user_id', $employeeIds)->where('kind', 'payroll')->where('status', 'paid')
            ->orderBy('period_end')->get(['id', 'user_id', 'period_start', 'period_end', 'source_snapshot'])->groupBy('user_id');
        $yearEntries = $allEntries->map(fn (Collection $entries) => $entries->filter(fn (AccountingEntry $entry) => $entry->period_end->year === $year)->values());
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31, 23, 59, 59, 'Asia/Manila');
        $attendanceEnd = $today->min($yearEnd)->toDateString();
        $attendance = AttendanceRecord::query()->whereIn('user_id', $employeeIds)->whereBetween('attendance_date', ["{$year}-01-01", $attendanceEnd])
            ->whereNotNull('total_minutes')->orderBy('attendance_date')->get()->groupBy('user_id');
        $monthlyBasic = array_fill(1, 12, 0);

        $rows = $employees->map(function (User $employee) use ($allEntries, $yearEntries, $attendance, &$monthlyBasic, $year) {
            $confirmedBasic = 0;
            $pendingBasic = 0;
            $eligibleMinutes = 0;
            $missing = 0;
            $entryIds = [];
            $lastPayrollDate = null;
            $lastAttendanceDate = null;
            $ranges = [];

            foreach ($yearEntries->get($employee->id, collect()) as $entry) {
                $line = $this->basicLine($entry);
                if (! $line) {
                    $missing++;

                    continue;
                }
                $amount = (int) $line['amount_cents'];
                $confirmedBasic += $amount;
                $eligibleMinutes += isset($line['minutes']) ? (int) $line['minutes'] : (int) round(((float) ($line['hours'] ?? 0)) * 60);
                $entryIds[] = $entry->id;
                $lastPayrollDate = $entry->period_end->toDateString();
                $monthlyBasic[$entry->period_end->month] += $amount;
                $ranges[] = [$entry->period_start->toDateString(), $entry->period_end->toDateString()];
            }

            $pendingDays = 0;
            $missingRateDays = 0;
            foreach ($attendance->get($employee->id, collect()) as $record) {
                $date = $record->attendance_date->toDateString();
                if (collect($ranges)->contains(fn ($range) => $date >= $range[0] && $date <= $range[1])) {
                    continue;
                }
                $minutes = $this->basicMinutes($record);
                if ($minutes <= 0) {
                    continue;
                }
                $rate = $this->rateForDate($allEntries->get($employee->id, collect()), $date);
                if ($rate === null) {
                    $missingRateDays++;

                    continue;
                }
                $amount = intdiv($rate * 100 * $minutes + 3000, 6000);
                $pendingBasic += $amount;
                $eligibleMinutes += $minutes;
                $monthlyBasic[(int) substr($date, 5, 2)] += $amount;
                $pendingDays++;
                $lastAttendanceDate = $date;
            }

            $basicCents = $confirmedBasic + $pendingBasic;

            return [
                'employee' => $employee->only(['id', 'name', 'username', 'role', 'status']),
                'year' => $year,
                'eligible_basic_cents' => $basicCents,
                'confirmed_basic_cents' => $confirmedBasic,
                'pending_basic_cents' => $pendingBasic,
                'eligible_minutes' => $eligibleMinutes,
                'thirteenth_month_cents' => (int) round($basicCents / 12),
                'confirmed_thirteenth_month_cents' => (int) round($confirmedBasic / 12),
                'daily_accrual_cents' => (int) round($pendingBasic / 12),
                'pending_attendance_days' => $pendingDays,
                'payroll_count' => count($entryIds),
                'payroll_entry_ids' => $entryIds,
                'last_payroll_date' => $lastPayrollDate,
                'last_attendance_date' => $lastAttendanceDate,
                'missing_payroll_count' => $missing,
                'missing_rate_days' => $missingRateDays,
                'data_complete' => $missing === 0 && $missingRateDays === 0,
            ];
        });

        $monthly = collect(range(1, 12))->map(fn (int $month) => [
            'month' => sprintf('%04d-%02d', $year, $month),
            'basic_cents' => $monthlyBasic[$month],
            'accrued_cents' => (int) round($monthlyBasic[$month] / 12),
        ])->all();
        $target = $rows->sum('thirteenth_month_cents');
        $monthlyTotal = array_sum(array_column($monthly, 'accrued_cents'));
        if ($target !== $monthlyTotal) {
            $lastMonth = collect($monthly)->keys()->last(fn ($index) => $monthly[$index]['basic_cents'] > 0) ?? 11;
            $monthly[$lastMonth]['accrued_cents'] += $target - $monthlyTotal;
        }

        return ['rows' => $rows, 'monthly' => $monthly];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rows(int $year, ?Collection $employees = null): Collection
    {
        return $this->report($year, $employees)['rows'];
    }

    /** @return array<string, mixed> */
    public function employee(User $employee, int $year): array
    {
        return $this->rows($year, collect([$employee]))->first();
    }

    private function basicLine(AccountingEntry $entry): ?array
    {
        $line = collect(data_get($entry->source_snapshot, 'payroll.earnings', []))->first(
            fn ($item) => mb_strtolower(trim((string) ($item['label'] ?? ''))) === 'basic pay'
        );

        return $line && is_numeric($line['amount_cents'] ?? null) ? $line : null;
    }

    private function rateForDate(Collection $entries, string $date): ?int
    {
        $entry = $entries->last(fn (AccountingEntry $item) => $item->period_end->toDateString() <= $date) ?? $entries->first();
        $rate = data_get($entry?->source_snapshot, 'payroll.basic_rate') ?? data_get($entry?->source_snapshot, 'payroll.inputs.basic_rate');
        if (! is_string($rate) && ! is_numeric($rate)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $rate, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function basicMinutes(AttendanceRecord $record): int
    {
        $worked = (int) ($record->worked_minutes ?? 0);
        $leave = (int) ($record->leave_minutes ?? 0);
        $overtime = (int) data_get($record->manual_hours, 'overtime_minutes', 0);
        if (! $record->manual_hours && data_get($record->approval_snapshot, 'overtime') && ! data_get($record->approval_snapshot, 'undertime')) {
            $scheduledEnd = data_get($record->schedule_snapshot, 'times.time_out');
            if ($scheduledEnd && $record->time_in && $record->time_out) {
                $from = max($record->time_in->getTimestamp(), CarbonImmutable::parse($scheduledEnd)->getTimestamp());
                $until = $record->time_out->getTimestamp();
                $seconds = max(0, $until - $from);
                if ($record->lunch_out && $record->lunch_in) {
                    $seconds -= max(0, min($until, $record->lunch_in->getTimestamp()) - max($from, $record->lunch_out->getTimestamp()));
                }
                $overtime = min($worked, intdiv(max(0, $seconds), 60));
            }
        }

        return max(0, $worked - $overtime) + $leave;
    }
}
