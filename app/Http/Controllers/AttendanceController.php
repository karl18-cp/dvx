<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceScheduleService;
use App\Services\TeamLeaderWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceScheduleService $schedules): Response
    {
        $actor = $request->user()->fresh();
        abort_unless($actor->status === 'active' && in_array($actor->role, ['admin', 'team_leader'], true), 403);
        $teamLeader = $actor->role === 'team_leader';
        $ownRecords = $teamLeader && $request->input('scope') === 'mine';
        $visibleIds = $teamLeader ? ($ownRecords ? [$actor->id] : app(TeamLeaderWorkspaceService::class)->members($actor)->pluck('users.id')->push($actor->id)->unique()->all()) : null;

        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $request->input('date') ?? now(config('attendance.timezone'))->toDateString();
        $records = AttendanceRecord::query()
            ->when($teamLeader, fn ($q) => $q->whereIn('user_id', $visibleIds))
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('user_id');

        $employees = User::query()
            ->when($teamLeader, fn ($q) => $q->whereIn('id', $visibleIds))
            ->with([
                'campaignSchedule.days',
                'overtimeRequests' => fn ($query) => $query->where('status', 'approved')->whereDate('request_date', $date),
                'undertimeRequests' => fn ($query) => $query->where('status', 'approved')->whereDate('request_date', $date),
                'leaveRequests' => fn ($query) => $query->where('status', 'approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date),
            ])
            ->whereNotNull('username')
            ->orderBy('username')
            ->get()
            ->map(function (User $user) use ($records, $schedules, $date, $actor): array {
                /** @var AttendanceRecord|null $record */
                $record = $schedules->calculate($user, $date, $records->get($user->id));
                $schedule = $record->schedule_snapshot;

                return [
                    'id' => $user->id,
                    'canEdit' => $actor->role === 'admin' || (in_array($user->role, ['agent', 'trainee'], true) && $user->id !== $actor->id),
                    'employeeId' => $user->username,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'position' => $this->positionLabel($user->role),
                    'team' => $user->team,
                    'status' => $record?->status ?? ($schedule['rest_day'] ? 'rest_day' : 'not_recorded'),
                    'schedule' => $schedule,
                    'approvals' => $record->approval_snapshot,
                    'totalMinutes' => $record->total_minutes,
                    'workedMinutes' => $record->worked_minutes,
                    'leaveMinutes' => $record->leave_minutes,
                    'actualTimes' => collect(['time_in', 'lunch_out', 'lunch_in', 'time_out'])->mapWithKeys(fn ($field) => [$field => $record?->getAttribute('actual_'.$field)?->toISOString()]),
                    'timeIn' => $record?->time_in?->toISOString(),
                    'lunchOut' => $record?->lunch_out?->toISOString(),
                    'lunchIn' => $record?->lunch_in?->toISOString(),
                    'timeOut' => $record?->time_out?->toISOString(),
                ];
            });

        return Inertia::render('attendance', [
            'canOverride' => true,
            'isTeamLeader' => $teamLeader,
            'attendanceScope' => $ownRecords ? 'mine' : 'team',
            'attendanceDate' => $date,
            'employees' => $employees,
        ]);
    }

    public function overrideTime(Request $request, User $employee, AttendanceScheduleService $schedules): RedirectResponse
    {
        $actor = $request->user()->fresh();
        abort_unless($actor->status === 'active' && ($actor->role === 'admin' || ($actor->role === 'team_leader' && app(TeamLeaderWorkspaceService::class)->members($actor)->whereKey($employee->id)->exists())), 403);

        $data = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'time_date' => ['nullable', 'date_format:Y-m-d'],
            'field' => ['required', Rule::in(['time_in', 'lunch_out', 'lunch_in', 'time_out'])],
            'time' => ['present', 'nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($employee, $data, $schedules, $actor): void {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $before = AttendanceRecord::where('user_id', $employee->id)->whereDate('attendance_date', $data['attendance_date'])->first()?->toArray();
            $schedules->record($employee, $data['attendance_date'], $data['field'], $data['time'], $data['time_date'] ?? null);
            $after = AttendanceRecord::where('user_id', $employee->id)->whereDate('attendance_date', $data['attendance_date'])->first()?->toArray();
            DB::table('assessment_activity_logs')->insert([
                'actor_id' => $actor->id, 'action' => 'Attendance time corrected', 'target_type' => User::class, 'target_id' => $employee->id,
                'metadata' => json_encode(['field' => $data['field'], 'date' => $data['attendance_date'], 'before' => $before, 'after' => $after], JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
        });

        return to_route('attendance', ['date' => $data['attendance_date']])
            ->with('status', 'Attendance time updated successfully.');
    }

    private function positionLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'team_leader' => 'Team Leader',
            'agent' => 'Agent',
            'it_admin' => 'IT Admin',
            'it_support' => 'IT Support',
            'it_developer' => 'IT Developer',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }
}
