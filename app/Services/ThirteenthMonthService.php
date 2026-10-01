<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class ThirteenthMonthService
{
    /** @return Collection<int, array<string, mixed>> */
    public function rows(int $year, ?Collection $employees = null): Collection
    {
        $employees ??= User::query()
            ->whereNotNull('username')
            ->where('role', '!=', 'trainee')
            ->get(['id', 'name', 'username', 'role', 'status']);

        $entries = AccountingEntry::query()
            ->where('kind', 'payroll')
            ->where('status', 'paid')
            ->whereBetween('period_end', ["{$year}-01-01", "{$year}-12-31"])
            ->orderBy('period_end')
            ->get(['id', 'user_id', 'period_end', 'source_snapshot'])
            ->groupBy('user_id');

        return $employees->map(function (User $employee) use ($entries, $year) {
            $basicCents = 0;
            $eligibleMinutes = 0;
            $missing = 0;
            $entryIds = [];
            $lastPayrollDate = null;

            foreach ($entries->get($employee->id, collect()) as $entry) {
                $line = collect(data_get($entry->source_snapshot, 'payroll.earnings', []))
                    ->first(fn ($item) => mb_strtolower(trim((string) ($item['label'] ?? ''))) === 'basic pay');
                if (! $line || ! is_numeric($line['amount_cents'] ?? null)) {
                    $missing++;

                    continue;
                }
                $basicCents += (int) $line['amount_cents'];
                $eligibleMinutes += isset($line['minutes'])
                    ? (int) $line['minutes']
                    : (int) round(((float) ($line['hours'] ?? 0)) * 60);
                $entryIds[] = $entry->id;
                $lastPayrollDate = $entry->period_end?->toDateString() ?? (string) $entry->period_end;
            }

            return [
                'employee' => $employee->only(['id', 'name', 'username', 'role', 'status']),
                'year' => $year,
                'eligible_basic_cents' => $basicCents,
                'eligible_minutes' => $eligibleMinutes,
                'thirteenth_month_cents' => (int) round($basicCents / 12),
                'payroll_count' => count($entryIds),
                'payroll_entry_ids' => $entryIds,
                'last_payroll_date' => $lastPayrollDate,
                'missing_payroll_count' => $missing,
                'data_complete' => $missing === 0,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function employee(User $employee, int $year): array
    {
        return $this->rows($year, collect([$employee]))->first();
    }
}
