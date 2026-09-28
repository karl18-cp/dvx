<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\TrainingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;

class TrainingAllowanceService
{
    public const PHASES = ['Oral training', 'Call training', 'Nesting'];

    public function calendar(TrainingPlan $plan): array
    {
        $date = CarbonImmutable::parse($plan->start_date->toDateString(), 'Asia/Manila');
        $rows = [];
        foreach ($plan->phases as $index => $phase) {
            for ($day = 0; $day < $phase['days']; $day++) {
                while (! in_array($date->isoWeekday(), $plan->weekdays, true)) {
                    $date = $date->addDay();
                }
                $rows[] = ['day' => count($rows) + 1, 'date' => $date->toDateString(), 'phase' => self::PHASES[$index], 'rate_cents' => $phase['rate_cents']];
                $date = $date->addDay();
            }
        }

        return $rows;
    }

    public function summary(TrainingPlan $plan, User $trainee): array
    {
        $calendar = $this->calendar($plan);
        $today = CarbonImmutable::now('Asia/Manila')->toDateString();
        $records = AttendanceRecord::where('user_id', $trainee->id)->whereDate('attendance_date', '>=', $calendar[0]['date'])->whereDate('attendance_date', '<=', $calendar[array_key_last($calendar)]['date'])->get()->keyBy(fn ($r) => $r->attendance_date->toDateString());
        $earned = 0;
        $qualified = 0;
        $first = 0;
        $readyDate = null;
        $reviewedDate = $trainee->training_reviewed_at ? CarbonImmutable::parse($trainee->training_reviewed_at)->setTimezone('Asia/Manila')->toDateString() : null;
        $rows = [];
        foreach ($calendar as $row) {
            $record = $records->get($row['date']);
            $record = $record ? app(AttendanceScheduleService::class)->calculate($trainee, $row['date'], $record) : null;
            $completed = $record && $record->time_in && $record->time_out && ($record->actual_time_out ?? $record->time_out)->lte(now()) && $record->worked_minutes > 0;
            $eligibleDate = $row['date'] <= $today && (! $reviewedDate || $row['date'] <= $reviewedDate);
            $eligible = $eligibleDate && $completed;
            $inFirst = false;
            if ($eligible) {
                $qualified++;
                $earned += $row['rate_cents'];
                $inFirst = $qualified <= $plan->first_allowance_day;
                if ($inFirst) {
                    $first += $row['rate_cents'];
                }
                if ($qualified === $plan->first_allowance_day) {
                    $readyDate = $row['date'];
                }
            }
            $rows[] = [...$row, 'worked_minutes' => $record?->worked_minutes ?? 0, 'eligible' => (bool) $eligible, 'amount_cents' => $eligible ? $row['rate_cents'] : 0, 'first_allowance' => $inFirst, 'attendance' => $completed ? 'Completed' : ($row['date'] > $today ? 'Upcoming' : ($record?->time_in ? 'Incomplete' : 'Not recorded'))];
        }

        return [
            'trainee' => $trainee->only(['id', 'name', 'username', 'training_status']),
            'rows' => $rows, 'qualified_days' => $qualified, 'earned_cents' => $earned,
            'first_allowance_cents' => $first, 'first_allowance_ready' => $readyDate !== null, 'first_allowance_date' => $readyDate,
            'projected_first_cents' => array_sum(array_column(array_slice($calendar, 0, $plan->first_allowance_day), 'rate_cents')),
            'projected_first_date' => $calendar[$plan->first_allowance_day - 1]['date'],
            'projected_total_cents' => array_sum(array_column($calendar, 'rate_cents')),
        ];
    }
}
