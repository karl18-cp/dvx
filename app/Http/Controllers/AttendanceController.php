<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $records = AttendanceRecord::query()
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('user_id');

        $employees = User::query()
            ->whereNotNull('username')
            ->orderBy('username')
            ->get()
            ->map(function (User $user) use ($records): array {
                /** @var AttendanceRecord|null $record */
                $record = $records->get($user->id);

                return [
                    'id' => $user->id,
                    'employeeId' => $user->username,
                    'name' => $user->name,
                    'position' => $this->positionLabel($user->role),
                    'team' => $user->team,
                    'status' => $record?->status ?? 'not_recorded',
                    'timeIn' => $record?->time_in?->toISOString(),
                    'lunchOut' => $record?->lunch_out?->toISOString(),
                    'lunchIn' => $record?->lunch_in?->toISOString(),
                    'timeOut' => $record?->time_out?->toISOString(),
                ];
            });

        return Inertia::render('attendance', [
            'attendanceDate' => $date,
            'employees' => $employees,
        ]);
    }

    public function overrideTime(Request $request, User $employee): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $data = $request->validate([
            'attendance_date' => ['required', 'date'],
            'field' => ['required', Rule::in(['time_in', 'lunch_out', 'lunch_in', 'time_out'])],
            'time' => ['nullable', 'date_format:H:i'],
        ]);

        $record = AttendanceRecord::query()
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', $data['attendance_date'])
            ->first();

        if (! $record) {
            $record = AttendanceRecord::query()->create([
                'user_id' => $employee->id,
                'attendance_date' => $data['attendance_date'],
                'status' => $data['field'] === 'time_in' && $data['time']
                    ? 'present'
                    : 'not_recorded',
            ]);
        }

        $record->setAttribute(
            $data['field'],
            $data['time']
                ? Carbon::parse($data['attendance_date'].' '.$data['time'])
                : null,
        );
        $record->save();

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
