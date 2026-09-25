<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AssessmentNotification;
use App\Models\AttendanceRecord;
use App\Models\EmployeeSanction;
use App\Models\EodReport;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

class WorkplaceNotificationService
{
    private array $activeKeys = [];

    public function sync(User $user): void
    {
        $this->activeKeys = [];
        $since = now()->subDays(30);
        $admin = $user->role === 'admin';
        $agentIds = $user->role === 'team_leader' ? app(TeamLeaderWorkspaceService::class)->members($user)->where('role', 'agent')->where('id', '!=', $user->id)->pluck('users.id')->all() : [];
        foreach (Announcement::where('updated_at', '>=', $since)->get() as $item) {
            $this->notify($user, 'announcement', $item->id.':'.$item->updated_at->getTimestamp(), $item->title, $item->body, '/announcements', $item->updated_at);
        }
        foreach (['overtime' => OvertimeRequest::class, 'undertime' => UndertimeRequest::class, 'leave' => LeaveRequest::class] as $type => $model) {
            $query = $model::with('user')->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhereIn('status', ['pending', 'needs_review']);
            });
            if (! $admin) {
                $query->where(function ($q) use ($user, $type, $agentIds) {
                    $q->where('user_id', $user->id);
                    if ($type === 'leave' && $agentIds) {
                        $q->orWhere(fn ($q) => $q->whereIn('user_id', $agentIds)->where('requires_leader_approval', true)->where('leader_status', 'pending')->whereIn('status', ['pending', 'needs_review']));
                    }
                });
            }
            foreach ($query->get() as $item) {
                if ($type === 'leave' && $item->requires_leader_approval && in_array($item->status, ['pending', 'needs_review'], true)) {
                    $date = $item->start_date->toDateString().' to '.$item->end_date->toDateString();
                    if (in_array($item->user_id, $agentIds, true) && $item->leader_status === 'pending') {
                        $this->notify($user, 'leave', $item->id.':initial-pending', 'Agent leave awaiting your review', $item->user->name.' · '.$date."\n".$item->reason, '/leave-requests?scope=team', $item->updated_at);
                    }
                    if ($item->user_id === $user->id && $item->leader_status === 'approved') {
                        $this->notify($user, 'leave', $item->id.':initial-approved', 'Leave forwarded for final admin approval', $date."\n".($item->leader_notes ?? ''), '/leave-requests', $item->updated_at);
                    }
                    if ($item->leader_status !== 'approved') {
                        continue;
                    }
                }
                if (in_array($item->status, ['pending', 'needs_review'], true) && ! $admin) {
                    continue;
                }
                if (! in_array($item->status, ['pending', 'needs_review'], true) && $item->user_id !== $user->id) {
                    continue;
                }
                $date = $type === 'leave' ? $item->start_date->toDateString().' to '.$item->end_date->toDateString() : $item->request_date->toDateString();
                $this->notify($user, $type, $item->id.':'.$item->status, ucfirst($type).' request '.(in_array($item->status, ['pending', 'needs_review'], true) ? 'awaiting review' : $item->status), ($item->user?->name ?? 'Employee').' · '.$date."\n".($item->reason ?? '').($item->review_notes ? "\nReview: ".$item->review_notes : '').($type === 'leave' && $item->leader_notes ? "\nTeam leader: ".$item->leader_notes : ''), $admin ? '/requests' : ($type === 'leave' ? '/leave-requests' : (in_array($user->role, ['agent', 'team_leader'], true) ? '/my-requests?type='.$type : null)), $item->updated_at);
            }
        }
        $sanctions = EmployeeSanction::where('created_at', '>=', $since)->when(! $admin, fn ($q) => $q->where('employee_id', $user->id));
        foreach ($sanctions->get() as $item) {
            $this->notify($user, 'sanction', (string) $item->id, 'Sanction issued: '.$item->sanction_name, $item->employee_name.' · '.$item->punishment."\n".($item->notes ?? ''), $admin ? '/sanctions' : null, $item->created_at);
        }
        if ($admin) {
            foreach (EodReport::where('created_at', '>=', $since)->where('user_id', '!=', $user->id)->get() as $item) {
                $this->notify($user, 'eod', (string) $item->id, 'EOD report submitted', $item->author_name.' · '.$item->report_date->toDateString()."\n".$item->summary, '/eod-reports', $item->created_at);
            }
        }
        $records = AttendanceRecord::with('user')->where('attendance_date', '>=', $since->toDateString())->when(! $admin, fn ($q) => $q->where('user_id', $user->id));
        foreach ($records->get() as $record) {
            $date = $record->attendance_date->toDateString();
            $onLeave = $record->status === 'on_leave' || LeaveRequest::where('user_id', $record->user_id)->where('status', 'approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists();
            $end = $record->schedule_snapshot['times']['time_out'] ?? null;
            $absent = ! $onLeave && ($record->status === 'absent' || (! $record->time_in && $end && ! ($record->schedule_snapshot['rest_day'] ?? false) && CarbonImmutable::parse($end)->isPast()));
            $type = $absent ? 'absence' : (! $onLeave && $record->status === 'late' ? 'late' : null);
            if ($type) {
                $this->notify($user, $type, (string) $record->id, $type === 'late' ? 'Late arrival recorded' : 'Attendance absence alert', ($record->user?->name ?? 'Employee').' · '.$date.($absent ? "\nNo time-in recorded for this completed shift." : "\nTime-in was after the scheduled start."), $admin ? '/attendance' : null, $record->updated_at);
            }
        }
        // Check only recent completed shifts against current assignments. Never
        // retroactively infer weeks of absences from a newly assigned schedule.
        $today = CarbonImmutable::now(config('attendance.timezone'))->startOfDay();
        $employees = User::where('status', 'active')->whereNotNull('campaign_schedule_id')
            ->when(! $admin, fn ($q) => $q->whereKey($user->id))
            ->with(['campaignSchedule.days', 'attendanceRecords' => fn ($q) => $q->where('attendance_date', '>=', $today->subDay()->toDateString()), 'leaveRequests' => fn ($q) => $q->where('status', 'approved')->where('end_date', '>=', $today->subDay()->toDateString())])->get();
        foreach ($employees as $employee) {
            foreach ([$today->subDay(), $today] as $day) {
                $date = $day->toDateString();
                if ($employee->attendanceRecords->contains(fn ($record) => $record->attendance_date->toDateString() === $date)
                    || $employee->leaveRequests->contains(fn ($leave) => $leave->start_date->toDateString() <= $date && $leave->end_date->toDateString() >= $date)) {
                    continue;
                }
                $snapshot = app(AttendanceScheduleService::class)->snapshot($employee, $date);
                $end = $snapshot['times']['time_out'] ?? null;
                $start = $snapshot['times']['time_in'] ?? null;
                if ($end && $start && CarbonImmutable::parse($end)->isPast() && $employee->created_at->lte(CarbonImmutable::parse($start))) {
                    $this->notify($user, 'absence', "missing:{$employee->id}:{$date}", 'Possible absence: missing attendance', $employee->name.' · '.$date."\nThe assigned shift has ended with no attendance record. Please verify this absence.", $admin ? '/attendance' : null, CarbonImmutable::parse($end));
                }
            }
        }
        // Remove alerts whose source was deleted, corrected, or is no longer
        // visible to this recipient (including a changed admin role).
        AssessmentNotification::where('recipient_id', $user->id)->where('deduplication_key', 'like', "workplace:{$user->id}:%")->whereNotIn('deduplication_key', $this->activeKeys)->delete();
    }

    private function notify(User $user, string $type, string $key, string $title, string $message, ?string $url, $date): void
    {
        $key = "workplace:{$user->id}:{$type}:{$key}";
        $this->activeKeys[] = $key;
        $notification = AssessmentNotification::firstOrCreate(['deduplication_key' => $key], ['recipient_id' => $user->id, 'type' => $type, 'title' => mb_substr($title, 0, 255), 'message' => $message, 'action_url' => $url]);
        if ($notification->wasRecentlyCreated) {
            $notification->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
        }
    }
}
