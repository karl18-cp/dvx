<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function authorize(User $actor): void
    {
        abort_unless(app(RolePermissionService::class)->can($actor, 'salaries.view'), 403);
    }

    public function authorizeFinance(User $actor): void
    {
        abort_unless(app(RolePermissionService::class)->can($actor, 'finance.manage'), 403);
    }

    public function authorizePayroll(User $actor): void
    {
        abort_unless(app(RolePermissionService::class)->can($actor, 'payroll.manage'), 403);
    }

    public function money(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function payroll(User $actor, array $data): AccountingEntry
    {
        $this->authorizePayroll($actor);

        return DB::transaction(function () use ($actor, $data) {
            $employee = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            abort_if($employee->role === 'trainee', 422, 'Use training allowances for trainee accounts.');
            if ($existing = AccountingEntry::where('request_key', $data['request_key'])->first()) {
                return $existing;
            }
            $calculation = app(PayrollCalculationService::class)->calculate($data, $employee);
            $data['period_start'] = $calculation['period_start'];
            $data['period_end'] = $calculation['period_end'];
            if (AccountingEntry::where('user_id', $employee->id)->where('kind', 'payroll')->where('status', '!=', 'void')->whereDate('period_start', '<=', $data['period_end'])->whereDate('period_end', '>=', $data['period_start'])->exists()) {
                throw ValidationException::withMessages(['period_start' => 'This employee already has a payroll entry overlapping this period. Review it or void it before replacing it.']);
            }
            $gross = $calculation['gross_cents'];
            $deduction = $calculation['deduction_cents'];
            if ($gross <= $deduction) {
                throw ValidationException::withMessages(['deduction' => 'Net pay must be greater than zero.']);
            }
            $entry = AccountingEntry::create([
                'request_key' => $data['request_key'], 'kind' => 'payroll', 'user_id' => $employee->id,
                'description' => $data['description'], 'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
                'gross_cents' => $gross, 'deduction_cents' => $deduction, 'net_cents' => $gross - $deduction,
                'notes' => $data['notes'] ?? null, 'created_by' => $actor->id,
                'source_snapshot' => ['employee_name' => $employee->name, 'employee_id' => $employee->username,
                    'basis' => 'Workbook formulas; attendance-derived hours and accounting-entered contribution amounts.',
                    'payroll' => $calculation],
            ]);
            $this->audit($actor, $entry, 'Accounting draft created');

            return $entry;
        });
    }

    public function allowancePreview(TrainingPlan $plan, User $trainee, string $cutoff): array
    {
        abort_unless($plan->trainees()->whereKey($trainee->id)->exists(), 404);
        $summary = app(TrainingAllowanceService::class)->summary($plan, $trainee);
        $entries = AccountingEntry::where('user_id', $trainee->id)->where('training_plan_id', $plan->id)->where('status', '!=', 'void')->get();
        $first = $entries->first(fn ($entry) => $entry->source_snapshot['first_release'] ?? false);
        $reserved = DB::table('accounting_allowance_days')->where('user_id', $trainee->id)->where('training_plan_id', $plan->id)->pluck('training_date')->all();
        $ready = $summary['first_allowance_ready'] && $summary['first_allowance_date'] <= $cutoff;
        $rows = array_values(array_filter($summary['rows'], fn ($row) => $row['eligible'] && $row['date'] <= $cutoff && ! in_array($row['date'], $reserved, true) && ($first || $row['first_allowance'])));

        return [...$summary, 'cutoff' => $cutoff, 'claim_rows' => $rows, 'claim_cents' => array_sum(array_column($rows, 'amount_cents')), 'first_release' => ! $first,
            'can_prepare' => $ready && (! $first || $first->status === 'paid') && count($rows) > 0 && array_sum(array_column($rows, 'amount_cents')) > 0,
            'pending_first' => $first && $first->status !== 'paid',
            'paid_cents' => $entries->where('status', 'paid')->sum('net_cents'),
            'reserved_cents' => $entries->whereIn('status', ['draft', 'for_review', 'approved', 'locked'])->sum('net_cents'),
        ];
    }

    public function allowance(User $actor, TrainingPlan $plan, User $trainee, array $data): AccountingEntry
    {
        $this->authorizePayroll($actor);

        return DB::transaction(function () use ($actor, $plan, $trainee, $data) {
            $plan = TrainingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $trainee = User::whereKey($trainee->id)->lockForUpdate()->firstOrFail();
            if ($existing = AccountingEntry::where('request_key', $data['request_key'])->first()) {
                return $existing;
            }
            $preview = $this->allowancePreview($plan, $trainee, $data['cutoff']);
            if (! $preview['can_prepare']) {
                throw ValidationException::withMessages(['allowance' => 'There are no eligible unpaid days to prepare yet. Check the first payout threshold and any pending allowance entry.']);
            }
            $rows = $preview['claim_rows'];
            $entry = AccountingEntry::create([
                'request_key' => $data['request_key'], 'kind' => 'training_allowance', 'user_id' => $trainee->id, 'training_plan_id' => $plan->id,
                'description' => ($preview['first_release'] ? 'First training allowance' : 'Training allowance'),
                'period_start' => $rows[0]['date'], 'period_end' => $rows[array_key_last($rows)]['date'],
                'gross_cents' => $preview['claim_cents'], 'net_cents' => $preview['claim_cents'], 'created_by' => $actor->id,
                'source_snapshot' => ['employee_name' => $trainee->name, 'employee_id' => $trainee->username, 'plan_name' => $plan->name, 'first_release' => $preview['first_release'], 'first_allowance_day' => $plan->first_allowance_day, 'rows' => $rows],
            ]);
            foreach ($rows as $row) {
                DB::table('accounting_allowance_days')->insert(['entry_id' => $entry->id, 'training_plan_id' => $plan->id, 'user_id' => $trainee->id, 'training_date' => $row['date']]);
            }
            $this->audit($actor, $entry, 'Accounting allowance draft created');

            return $entry;
        });
    }

    private function verifyAllowance(AccountingEntry $entry): void
    {
        if ($entry->kind !== 'training_allowance') {
            return;
        }
        $plan = TrainingPlan::whereKey($entry->training_plan_id)->lockForUpdate()->firstOrFail();
        $trainee = User::whereKey($entry->user_id)->lockForUpdate()->firstOrFail();
        $summary = app(TrainingAllowanceService::class)->summary($plan, $trainee);
        $rows = collect($summary['rows'])->keyBy('date');
        if (($entry->source_snapshot['first_release'] ?? false) && $entry->source_snapshot['first_allowance_day'] !== $plan->first_allowance_day) {
            throw ValidationException::withMessages(['entry' => 'The first allowance threshold changed. Void this draft and prepare it again.']);
        }
        foreach ($entry->source_snapshot['rows'] as $snapshot) {
            $row = $rows->get($snapshot['date']);
            if (! $row || ! $row['eligible'] || $row['amount_cents'] !== $snapshot['amount_cents']) {
                throw ValidationException::withMessages(['entry' => 'Attendance or allowance rates changed after this draft. Void it and prepare a new entry.']);
            }
        }
        if (! $summary['first_allowance_ready']) {
            throw ValidationException::withMessages(['entry' => 'The first allowance attendance threshold is no longer met. Review this draft.']);
        }
    }

    private function verifyPayroll(AccountingEntry $entry): void
    {
        if ($entry->kind !== 'payroll') {
            return;
        }
        $snapshot = $entry->source_snapshot['payroll'] ?? null;
        if (($snapshot['version'] ?? 0) !== 2) {
            throw ValidationException::withMessages(['entry' => 'This payroll uses manually entered hours. Void it and prepare a new attendance-linked draft.']);
        }
        $employee = User::whereKey($entry->user_id)->lockForUpdate()->firstOrFail();
        $current = app(PayrollCalculationService::class)->calculate($snapshot['inputs'], $employee);
        if ($current['attendance']['fingerprint'] !== $snapshot['attendance']['fingerprint'] || $current['gross_cents'] !== $entry->gross_cents || $current['deduction_cents'] !== $entry->deduction_cents) {
            throw ValidationException::withMessages(['entry' => 'Attendance or approved requests changed. Void this unpaid draft and prepare it again before approving or recording payment.']);
        }
    }

    public function transition(User $actor, AccountingEntry $entry, array $data): void
    {
        $this->authorizePayroll($actor);
        DB::transaction(function () use ($actor, $entry, $data) {
            $entry = AccountingEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $before = $entry->status;
            if ($data['action'] === 'submit') {
                if ($before !== 'draft') {
                    throw ValidationException::withMessages(['entry' => 'Only a draft can be submitted for review.']);
                }
                $this->verifyAllowance($entry);
                $this->verifyPayroll($entry);
                $entry->fill(['status' => 'for_review', 'submitted_by' => $actor->id, 'submitted_at' => now()]);
            } elseif ($data['action'] === 'approve') {
                if ($before !== 'for_review') {
                    throw ValidationException::withMessages(['entry' => 'Only an entry that is for review can be approved.']);
                }
                $this->verifyAllowance($entry);
                $this->verifyPayroll($entry);
                $entry->fill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            } elseif ($data['action'] === 'lock') {
                if ($before !== 'approved') {
                    throw ValidationException::withMessages(['entry' => 'Only an approved entry can be locked.']);
                }
                $this->verifyAllowance($entry);
                $this->verifyPayroll($entry);
                $entry->fill(['status' => 'locked', 'locked_by' => $actor->id, 'locked_at' => now()]);
            } elseif ($data['action'] === 'pay') {
                if ($before !== 'locked') {
                    throw ValidationException::withMessages(['entry' => 'Lock the approved entry before recording payment.']);
                }
                $entry->fill(['status' => 'paid', 'paid_by' => $actor->id, 'paid_at' => $data['paid_at'], 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference']]);
            } elseif ($data['action'] === 'return') {
                if (! in_array($before, ['for_review', 'approved'], true)) {
                    throw ValidationException::withMessages(['entry' => 'Only an entry under review or approved can be returned to draft.']);
                }
                $entry->fill([
                    'status' => 'draft', 'submitted_by' => null, 'submitted_at' => null,
                    'approved_by' => null, 'approved_at' => null,
                ]);
            } else {
                if (! in_array($before, ['draft', 'for_review'], true)) {
                    throw ValidationException::withMessages(['entry' => 'Only a draft or entry for review can be voided. Locked records require a documented adjustment or reversal.']);
                }
                $entry->fill(['status' => 'void', 'voided_by' => $actor->id, 'voided_at' => now(), 'void_reason' => $data['void_reason']]);
                DB::table('accounting_allowance_days')->where('entry_id', $entry->id)->delete();
            }
            $entry->save();
            $this->audit($actor, $entry, 'Accounting entry '.$entry->status, [
                'previous_status' => $before,
                'transition_reason' => $data['transition_reason'] ?? $data['void_reason'] ?? null,
            ]);
            if ($entry->status === 'paid') {
                app(AssessmentNotificationService::class)->createForUser(
                    $entry->employee,
                    'accounting:'.$entry->id.':paid',
                    'accounting_payment',
                    'Payment recorded: '.$entry->description,
                    'Accounting recorded PHP '.number_format($entry->net_cents / 100, 2).' for '.$entry->period_start->toDateString().' to '.$entry->period_end->toDateString().'. Payment date: '.$entry->paid_at->toDateString().'. Reference: '.$entry->payment_reference.'.',
                    $entry->kind === 'payroll' ? '/my-payslips?entry='.$entry->id : null,
                );
            }
        });
    }

    public function correction(User $actor, AccountingEntry $original, array $data): AccountingEntry
    {
        $this->authorizePayroll($actor);

        return DB::transaction(function () use ($actor, $original, $data) {
            $original = AccountingEntry::whereKey($original->id)->lockForUpdate()->firstOrFail();
            if (! in_array($original->status, ['locked', 'paid'], true)) {
                throw ValidationException::withMessages(['entry' => 'Adjustments and reversals are only used after an entry is locked. Return or void an earlier entry instead.']);
            }
            if ($original->kind !== 'payroll') {
                throw ValidationException::withMessages(['entry' => 'Documented payroll corrections can only be created for payroll records.']);
            }
            if ($existing = AccountingEntry::where('request_key', $data['request_key'])->first()) {
                return $existing;
            }
            if ($data['correction_type'] === 'reversal' && AccountingEntry::where('parent_entry_id', $original->id)->where('correction_type', 'reversal')->where('status', '!=', 'void')->exists()) {
                throw ValidationException::withMessages(['entry' => 'This payroll already has an active reversal record.']);
            }

            $amount = $data['correction_type'] === 'reversal' ? $original->net_cents : $this->money($data['amount']);
            if ($amount < 1) {
                throw ValidationException::withMessages(['amount' => 'The correction amount must be greater than zero.']);
            }
            $direction = $data['correction_type'] === 'reversal' ? 'debit' : $data['correction_direction'];
            $entry = AccountingEntry::create([
                'request_key' => $data['request_key'],
                'kind' => 'payroll_adjustment',
                'user_id' => $original->user_id,
                'parent_entry_id' => $original->id,
                'correction_type' => $data['correction_type'],
                'correction_direction' => $direction,
                'correction_reason' => $data['reason'],
                'description' => $data['description'],
                'period_start' => $original->period_start,
                'period_end' => $original->period_end,
                'gross_cents' => $amount,
                'deduction_cents' => 0,
                'net_cents' => $amount,
                'notes' => $data['reason'],
                'created_by' => $actor->id,
                'source_snapshot' => [
                    'employee_name' => $original->source_snapshot['employee_name'] ?? $original->employee->name,
                    'employee_id' => $original->source_snapshot['employee_id'] ?? $original->employee->username,
                    'original_entry_id' => $original->id,
                    'original_status' => $original->status,
                    'original_net_cents' => $original->net_cents,
                ],
            ]);
            $this->audit($actor, $entry, 'Payroll '.$data['correction_type'].' draft created', [
                'original_entry_id' => $original->id,
                'direction' => $direction,
                'amount_cents' => $amount,
                'reason' => $data['reason'],
            ]);

            return $entry;
        });
    }

    private function audit(User $actor, AccountingEntry $entry, string $action, array $extra = []): void
    {
        app(AuditLogService::class)->record($actor, 'payroll', $action, $entry, metadata: [...$extra, 'entry' => $entry->toArray()], subjectId: $entry->user_id);
    }
}
