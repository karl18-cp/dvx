<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeNumberService
{
    private function highest(string $prefix): int
    {
        return User::query()->where('username', 'like', $prefix.'%')->pluck('username')
            ->map(fn ($username) => preg_match('/^'.$prefix.'(\d+)$/i', (string) $username, $matches) ? (int) $matches[1] : 0)->max() ?? 0;
    }

    public function next(bool $trainee = false, bool $allocate = false): string
    {
        $prefix = $trainee ? 'DVXTR' : 'DVX';
        // Allocation is called inside the account creation / graduation transaction.
        $query = DB::table('employee_number_sequences')->where('prefix', $prefix);
        $sequence = ($allocate ? $query->lockForUpdate() : $query)->first();
        $number = max($trainee ? 0 : 1, $sequence->last_number, $this->highest($prefix)) + 1;
        if ($allocate) {
            DB::table('employee_number_sequences')->where('prefix', $prefix)->update(['last_number' => $number]);
        }

        return sprintf('%s%03d', $prefix, $number);
    }
}
