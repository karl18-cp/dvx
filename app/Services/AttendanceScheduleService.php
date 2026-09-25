<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceScheduleService
{
    public function snapshot(User $employee, string $date): array
    {
        $employee->loadMissing('campaignSchedule.days');
        $schedule = $employee->campaignSchedule;
        $day = $schedule?->days->firstWhere('day', Carbon::parse($date)->isoWeekday());
        $snapshot = ['name' => $schedule?->name, 'timezone' => config('attendance.timezone'), 'rest_day' => (bool) ($schedule && (! $day || $day->no_schedule)), 'times' => []];
        if (! $day || $day->no_schedule) {
            return $snapshot;
        }
        $start = Carbon::parse($date.' '.$day->time_in, $snapshot['timezone']);
        foreach (['time_in' => 'time_in', 'lunch_out' => 'break_start', 'lunch_in' => 'break_end', 'time_out' => 'time_out'] as $field => $column) {
            if (! $day->$column) {
                continue;
            }
            $time = Carbon::parse($date.' '.$day->$column, $snapshot['timezone']);
            if ($time->lt($start) || ($field === 'time_out' && $time->eq($start))) {
                $time->addDay();
            }
            $snapshot['times'][$field] = $time->toISOString();
        }

        return $snapshot;
    }

    public function record(User $employee, string $date, string $field, ?string $time, ?string $timeDate = null): void
    {
        DB::transaction(function () use ($employee, $date, $field, $time, $timeDate): void {
            $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
            $record = AttendanceRecord::query()->where('user_id', $employee->id)->whereDate('attendance_date', $date)->first()
                ?? new AttendanceRecord(['user_id' => $employee->id, 'attendance_date' => $date]);
            $snapshot = $record->schedule_snapshot ?? $this->snapshot($employee, $date);
            $record->schedule_snapshot = $snapshot;
            $approved = $this->approvals($employee, $date, $snapshot);
            $anchorValue = $field === 'time_out' ? ($approved['time_out'] ?? $snapshot['times'][$field] ?? null) : ($snapshot['times'][$field] ?? null);
            $anchor = $anchorValue ? Carbon::parse($anchorValue) : null;
            $actual = $time ? Carbon::parse(($timeDate ?? $date).' '.$time, $snapshot['timezone']) : null;
            // A time-only entry is placed on the closest day to this shift's event.
            if ($actual && $anchor && ! $timeDate) {
                $actual = collect([$actual->copy()->subDay(), $actual, $actual->copy()->addDay()])
                    ->sortBy(fn (Carbon $candidate) => abs($candidate->getTimestamp() - $anchor->getTimestamp()))->first();
            }
            $record->setAttribute('actual_'.$field, $actual?->utc());
            $record->setAttribute($field, $actual?->copy()->utc());
            $previousActual = null;
            foreach (['time_in', 'lunch_out', 'lunch_in', 'time_out'] as $event) {
                $actualValue = $record->getAttribute('actual_'.$event) ?? $record->getAttribute($event);
                if ($actualValue && $previousActual && $actualValue->lt($previousActual)) {
                    throw ValidationException::withMessages(['time' => 'The recorded times must follow this order: time in, break start, break end, time out. Check the time and date.']);
                }
                $previousActual = $actualValue ?? $previousActual;
            }
            $this->calculate($employee, $date, $record);
            $record->save();
        });
    }

    public function approvals(User $employee, string $date, array $schedule): array
    {
        $employee->loadMissing(['overtimeRequests', 'undertimeRequests', 'leaveRequests']);
        $leaves = $employee->leaveRequests->filter(fn ($item) => $item->status === 'approved' && $item->start_date->toDateString() <= $date && $item->end_date->toDateString() >= $date);
        $leave = $leaves->sortByDesc(fn ($item) => $item->isPaid())->first();
        $result = ['leave' => $leave ? ['id' => $leave->id, 'type' => $leave->leave_type, 'paid' => $leave->isPaid()] : null, 'overtime' => null, 'undertime' => null, 'time_out' => null];
        if ($leave) {
            return $result;
        }
        foreach (['overtime' => 'overtimeRequests', 'undertime' => 'undertimeRequests'] as $type => $relation) {
            foreach ($employee->$relation->filter(fn ($item) => $item->status === 'approved' && $item->request_date->toDateString() === $date) as $request) {
                $end = $this->approvedEnd($schedule, $date, $request->request_time, $type);
                if (! $end) {
                    continue;
                }
                $current = $result[$type]['time'] ?? null;
                if (! $current || ($type === 'overtime' ? $end->gt(Carbon::parse($current)) : $end->lt(Carbon::parse($current)))) {
                    $result[$type] = ['id' => $request->id, 'time' => $end->toISOString()];
                }
            }
        }
        // Legacy conflicting approvals use the earlier authorized departure; new conflicts are rejected.
        $result['time_out'] = $result['undertime']['time'] ?? $result['overtime']['time'] ?? null;

        return $result;
    }

    public function approvedEnd(array $schedule, string $date, string $time, string $type): ?Carbon
    {
        if (empty($schedule['times']['time_in']) || empty($schedule['times']['time_out'])) {
            return null;
        }
        $start = Carbon::parse($schedule['times']['time_in']);
        $end = Carbon::parse($schedule['times']['time_out']);
        $candidate = Carbon::parse($date.' '.$time, $schedule['timezone']);
        while ($candidate->lte($start)) {
            $candidate->addDay();
        }
        if ($type === 'overtime' && $candidate->lt($end)) {
            $candidate->addDay();
        }
        if ($type === 'undertime') {
            return $candidate->lt($end) ? $candidate : null;
        }

        return $candidate->gt($end) && $candidate->lte($start->copy()->addDay()) ? $candidate : null;
    }

    public function calculate(User $employee, string $date, ?AttendanceRecord $record = null): AttendanceRecord
    {
        $record ??= new AttendanceRecord(['user_id' => $employee->id, 'attendance_date' => $date]);
        $schedule = $record->schedule_snapshot ?? $this->snapshot($employee, $date);
        $record->schedule_snapshot = $schedule;
        $approved = $this->approvals($employee, $date, $schedule);
        $record->approval_snapshot = $approved;
        $times = $schedule['times'];
        if ($approved['time_out']) {
            $times['time_out'] = $approved['time_out'];
        }
        foreach (['time_in', 'lunch_out', 'lunch_in', 'time_out'] as $field) {
            $actual = $record->getAttribute('actual_'.$field) ?? $record->getAttribute($field);
            if (! $actual) {
                continue;
            }
            $actual = $actual->copy();
            $anchor = isset($times[$field]) ? Carbon::parse($times[$field]) : null;
            $startsWork = in_array($field, ['time_in', 'lunch_in'], true);
            $effective = $anchor && (($startsWork && $actual->lt($anchor)) || (! $startsWork && $actual->gt($anchor))) ? $anchor : $actual;
            $record->setAttribute($field, $effective->utc());
        }
        $start = isset($schedule['times']['time_in']) ? Carbon::parse($schedule['times']['time_in']) : null;
        $end = isset($schedule['times']['time_out']) ? Carbon::parse($schedule['times']['time_out']) : null;
        $breakStart = isset($times['lunch_out']) ? Carbon::parse($times['lunch_out']) : null;
        $breakEnd = isset($times['lunch_in']) ? Carbon::parse($times['lunch_in']) : null;
        $record->leave_minutes = 0;
        if ($approved['leave']) {
            $record->status = 'on_leave';
            $record->worked_minutes = 0;
            $record->leave_minutes = $approved['leave']['paid'] && $start && $end ? $this->netMinutes($start, $end, $breakStart, $breakEnd) : 0;
            $record->total_minutes = $record->leave_minutes;

            return $record;
        }
        $record->status = $record->time_in ? ($start && $record->time_in->gt($start) ? 'late' : 'present') : ($schedule['rest_day'] ? 'rest_day' : 'not_recorded');
        // Deduct the scheduled break even if punches are missing; longer actual breaks also reduce hours.
        $breakStart = $record->lunch_out ?? $breakStart;
        $breakEnd = $record->lunch_in ?? $breakEnd;
        $record->worked_minutes = $record->time_in && $record->time_out && (bool) $breakStart === (bool) $breakEnd
            ? $this->netMinutes($record->time_in, $record->time_out, $breakStart, $breakEnd) : null;
        $record->total_minutes = $record->worked_minutes;

        return $record;
    }

    private function netMinutes(CarbonInterface $start, CarbonInterface $end, ?CarbonInterface $breakStart, ?CarbonInterface $breakEnd): int
    {
        $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());
        if ($breakStart && $breakEnd) {
            $seconds -= max(0, min($end->getTimestamp(), $breakEnd->getTimestamp()) - max($start->getTimestamp(), $breakStart->getTimestamp()));
        }

        return (int) floor(max(0, $seconds) / 60);
    }

    public function syncRequest(LeaveRequest|OvertimeRequest|UndertimeRequest $request): void
    {
        $employee = User::query()->findOrFail($request->user_id);
        $from = $request instanceof LeaveRequest ? $request->start_date : $request->request_date;
        $until = $request instanceof LeaveRequest ? $request->end_date : $request->request_date;
        for ($day = $from->copy(); $day->lte($until); $day = $day->addDay()) {
            $date = $day->toDateString();
            $record = AttendanceRecord::query()->where('user_id', $employee->id)->whereDate('attendance_date', $date)->first();
            $this->calculate($employee, $date, $record)->save();
        }
    }
}
