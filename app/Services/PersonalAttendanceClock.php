<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PersonalAttendanceClock
{
    public function context(User $user): array
    {
        $now = CarbonImmutable::now(config('attendance.timezone'));
        $schedules = app(AttendanceScheduleService::class);
        $open = AttendanceRecord::where('user_id', $user->id)->whereNotNull('actual_time_in')->whereNull('actual_time_out')->latest('attendance_date')->first();
        if ($open) {
            $date = $open->attendance_date->toDateString();
            $snapshot = $open->schedule_snapshot ?? $schedules->snapshot($user, $date);
            $problem = $open->actual_time_in->lt($now->subHours(24)) ? 'Your previous shift is still open. Ask an administrator to correct it before continuing.' : null;
        } else {
            $candidates = [];
            foreach ([$now->subDay(), $now, $now->addDay()] as $day) {
                $snapshot = $schedules->snapshot($user, $day->toDateString());
                if (empty($snapshot['times']['time_in']) || empty($snapshot['times']['time_out'])) {
                    continue;
                }
                $start = CarbonImmutable::parse($snapshot['times']['time_in']);
                $end = CarbonImmutable::parse($snapshot['times']['time_out']);
                if ($now->between($start->subHours(4), $end)) {
                    $candidates[] = ['date' => $day->toDateString(), 'snapshot' => $snapshot, 'distance' => abs($start->getTimestamp() - $now->getTimestamp())];
                }
            }
            usort($candidates, fn ($a, $b) => $a['distance'] <=> $b['distance']);
            $date = $candidates[0]['date'] ?? $now->toDateString();
            $snapshot = $candidates[0]['snapshot'] ?? $schedules->snapshot($user, $date);
            $open = AttendanceRecord::where('user_id', $user->id)->whereDate('attendance_date', $date)->first();
            $problem = $candidates ? null : 'No working shift is available now. You can time in up to four hours before your assigned shift.';
        }
        $record = $schedules->calculate($user, $date, $open);
        if ($record->approval_snapshot['leave']) {
            $problem = 'You have approved leave for this shift. Contact an administrator if you need to work.';
        }
        $times = [];
        foreach (['time_in', 'lunch_out', 'lunch_in', 'time_out'] as $field) {
            $times[$field] = $record->getAttribute('actual_'.$field)?->toISOString();
        }
        $actions = [];
        if (! $problem && ! $times['time_out']) {
            if (! $times['time_in']) {
                $actions = ['time_in'];
            } elseif ($times['lunch_out'] && ! $times['lunch_in']) {
                $actions = ['lunch_in'];
            } else {
                $actions = $times['lunch_in'] ? ['time_out'] : ['lunch_out', 'time_out'];
            }
        }

        return ['date' => $date, 'now' => $now->toISOString(), 'schedule' => $snapshot, 'times' => $times, 'actions' => $actions, 'problem' => $problem, 'totalMinutes' => $record->total_minutes, 'faceEnrolled' => $user->faceCredential?->model_version === config('face.model_version')];
    }

    public function assertAction(User $user, string $action, ?string $date = null): array
    {
        $context = $this->context($user);
        if (! in_array($action, $context['actions'], true) || ($date !== null && $date !== $context['date'])) {
            throw ValidationException::withMessages(['action' => $context['problem'] ?? 'This attendance action is no longer available. Refresh your attendance.']);
        }

        return $context;
    }

    public function record(User $user, string $action, string $date): void
    {
        $now = CarbonImmutable::now(config('attendance.timezone'));
        app(AttendanceScheduleService::class)->record($user, $date, $action, $now->format('H:i:s'), $now->toDateString());
    }
}
