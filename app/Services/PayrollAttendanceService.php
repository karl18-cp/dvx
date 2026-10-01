<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PayrollAttendanceService
{
    public function period(array $data): array
    {
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['pay_month']);
        $monthEnd = $data['payout'] === 'month_end';

        return ['period_start' => ($monthEnd ? $month->day(11) : $month->subMonth()->day(26))->toDateString(),
            'period_end' => $month->day($monthEnd ? 25 : 10)->toDateString(),
            'pay_date' => ($monthEnd ? $month->endOfMonth() : $month->day(15))->toDateString()];
    }

    public function summary(User $employee, array $data): array
    {
        $period = $this->period($data);
        $holidayDates = [];
        foreach (['regular_holidays', 'special_holidays'] as $field) {
            $holidayDates[$field] = array_values(array_unique(array_filter(array_map('trim', explode(',', $data[$field] ?? '')))));
            foreach ($holidayDates[$field] as $date) {
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! CarbonImmutable::hasFormat($date, 'Y-m-d') || $date < $period['period_start'] || $date > $period['period_end']) {
                    throw ValidationException::withMessages([$field => 'Use valid YYYY-MM-DD dates within the selected attendance cutoff.']);
                }
            }
        }
        if (array_intersect(...array_values($holidayDates))) {
            throw ValidationException::withMessages(['regular_holidays' => 'A date cannot be both a regular and special non-working holiday.']);
        }
        // Always reload approvals; payroll must not use a previously loaded relationship cache.
        $employee->load(['campaignSchedule.days', 'leaveRequests', 'overtimeRequests', 'undertimeRequests']);
        $records = AttendanceRecord::where('user_id', $employee->id)->whereDate('attendance_date', '>=', $period['period_start'])->whereDate('attendance_date', '<=', $period['period_end'])->get()->keyBy(fn ($r) => $r->attendance_date->toDateString());
        $scheduleService = app(AttendanceScheduleService::class);
        $totals = array_fill_keys(['basic_hours', 'allowance_hours', 'night_hours', 'overtime_hours', 'rest_hours', 'holiday_hours'], 0);
        $rows = [];
        $warnings = [];
        for ($day = CarbonImmutable::parse($period['period_start']); $day->toDateString() <= $period['period_end']; $day = $day->addDay()) {
            $date = $day->toDateString();
            // calculate() applies the same schedule caps, approvals and unpaid breaks as attendance, without writing records.
            $record = $scheduleService->calculate($employee, $date, $records->get($date));
            $minutes = array_fill_keys(array_keys($totals), 0);
            $paidLeave = (int) $record->leave_minutes;
            $worked = (int) $record->worked_minutes;
            $schedule = $record->schedule_snapshot;
            $times = $schedule['times'];
            $timezone = $schedule['timezone'] ?? config('attendance.timezone');
            $segments = [];
            if ($worked > 0 && $record->time_in && $record->time_out) {
                $start = $record->time_in->getTimestamp();
                $end = $record->time_out->getTimestamp();
                $breakStart = $record->lunch_out?->getTimestamp() ?? (isset($times['lunch_out']) ? CarbonImmutable::parse($times['lunch_out'])->getTimestamp() : null);
                $breakEnd = $record->lunch_in?->getTimestamp() ?? (isset($times['lunch_in']) ? CarbonImmutable::parse($times['lunch_in'])->getTimestamp() : null);
                if ($breakStart !== null && $breakEnd !== null && $breakEnd > $breakStart) {
                    if (min($end, $breakStart) > $start) {
                        $segments[] = [$start, min($end, $breakStart)];
                    }
                    if ($end > max($start, $breakEnd)) {
                        $segments[] = [max($start, $breakEnd), $end];
                    }
                } else {
                    $segments[] = [$start, $end];
                }
                $scheduledEnd = isset($times['time_out']) ? CarbonImmutable::parse($times['time_out'])->getTimestamp() : null;
                $otSeconds = 0;
                $nightSeconds = 0;
                foreach ($segments as [$from, $until]) {
                    if ($scheduledEnd && ! empty($record->approval_snapshot['overtime']) && empty($record->approval_snapshot['undertime'])) {
                        $otSeconds += max(0, $until - max($from, $scheduledEnd));
                    }
                    for ($night = CarbonImmutable::createFromTimestamp($from, $timezone)->startOfDay()->subDay(); $night->getTimestamp() < $until; $night = $night->addDay()) {
                        $nightSeconds += max(0, min($until, $night->addDay()->hour(6)->getTimestamp()) - max($from, $night->hour(22)->getTimestamp()));
                    }
                }
                $minutes['overtime_hours'] = min($worked, intdiv($otSeconds, 60));
                $minutes['night_hours'] = min($worked, intdiv($nightSeconds, 60));
                if (in_array($date, $holidayDates['regular_holidays'], true)) {
                    $minutes['holiday_hours'] = $worked;
                } elseif (($schedule['rest_day'] ?? false) || in_array($date, $holidayDates['special_holidays'], true)) {
                    $minutes['rest_hours'] = $worked;
                }
            }
            if ($record->manual_hours) {
                $minutes['overtime_hours'] = min($worked, $record->manual_hours['overtime_minutes']);
                $minutes['night_hours'] = min($worked, $record->manual_hours['night_minutes']);
                $minutes['holiday_hours'] = in_array($date, $holidayDates['regular_holidays'], true) ? $worked : 0;
                $minutes['rest_hours'] = ! $minutes['holiday_hours'] && (($schedule['rest_day'] ?? false) || in_array($date, $holidayDates['special_holidays'], true)) ? $worked : 0;
            }
            $minutes['basic_hours'] = max(0, $worked - $minutes['overtime_hours']) + $paidLeave;
            $minutes['allowance_hours'] = $minutes['basic_hours'];
            foreach ($minutes as $key => $value) {
                $totals[$key] += $value;
            }
            if ($record->total_minutes === null && ($record->time_in || $record->time_out || $record->lunch_out || $record->lunch_in)) {
                $warnings[] = $date.': incomplete attendance; no hours credited.';
            }
            if ($record->exists || $record->status === 'on_leave') {
                $rows[] = ['date' => $date, 'status' => $record->status, 'worked_minutes' => $worked, 'leave_minutes' => $paidLeave,
                    'total_minutes' => (int) $record->total_minutes, 'minutes' => $minutes,
                    'time_in' => $record->time_in?->toISOString(), 'time_out' => $record->time_out?->toISOString(),
                    'lunch_out' => $record->lunch_out?->toISOString(), 'lunch_in' => $record->lunch_in?->toISOString(),
                    'schedule' => $schedule, 'approvals' => $record->approval_snapshot, ...($record->manual_hours ? ['manual_hours' => $record->manual_hours] : [])];
            }
        }

        return [...$period, 'minutes' => $totals, 'hours' => array_map(fn ($value) => number_format($value / 60, 4, '.', ''), $totals),
            'rows' => $rows, 'warnings' => $warnings, 'fingerprint' => hash('sha256', json_encode([$totals, $rows, $holidayDates], JSON_THROW_ON_ERROR))];
    }
}
