<?php

namespace App\Http\Controllers;

use App\Models\EmployeeFormResponse;
use App\Models\EmployeeSanction;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\SatisfactionRating;
use App\Models\UndertimeRequest;
use App\Models\User;
use App\Services\AttendanceScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PersonalWorkspaceController extends Controller
{
    private function actor(Request $request): User
    {
        $user = $request->user()->fresh();
        abort_unless($user->status === 'active' && in_array($user->role, ['agent', 'team_leader'], true), 403);

        return $user;
    }

    public function records(Request $request)
    {
        $user = $this->actor($request);
        $data = $request->validate(['tab' => ['nullable', Rule::in(['sanctions', 'ratings', 'responses'])]]);
        $tab = $data['tab'] ?? 'sanctions';
        $query = match ($tab) {
            'sanctions' => EmployeeSanction::where('employee_id', $user->id)->select(['id', 'sanction_name', 'sanction_description', 'punishment', 'notes', 'issuer_name', 'created_at']),
            'ratings' => SatisfactionRating::where('employee_id', $user->id)->select(['id', 'quality', 'productivity', 'attendance', 'communication', 'professionalism', 'average', 'comments', 'reviewer_name', 'created_at']),
            'responses' => EmployeeFormResponse::where('employee_id', $user->id)->select(['id', 'form_title', 'answers', 'points', 'created_at']),
        };

        return Inertia::render('personal/records', ['tab' => $tab, 'records' => $query->latest('id')->paginate(15)->withQueryString()]);
    }

    public function requests(Request $request)
    {
        $user = $this->actor($request);
        $data = $request->validate(['type' => ['nullable', Rule::in(['overtime', 'undertime'])]]);
        $type = $data['type'] ?? 'overtime';
        $model = $type === 'overtime' ? OvertimeRequest::class : UndertimeRequest::class;

        return Inertia::render('personal/requests', [
            'type' => $type, 'today' => now(config('attendance.timezone'))->toDateString(),
            'statusMessage' => $request->session()->get('status'),
            'requests' => $model::where('user_id', $user->id)->latest('id')->paginate(15, ['id', 'request_date', 'request_time', 'reason', 'status', 'review_notes', 'created_at'])->withQueryString(),
        ]);
    }

    public function storeRequest(Request $request, AttendanceScheduleService $schedules)
    {
        $user = $this->actor($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['overtime', 'undertime'])],
            'request_date' => ['required', 'date_format:Y-m-d'],
            'request_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($user, $data, $schedules) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === 'active' && in_array($user->role, ['agent', 'team_leader'], true), 403);
            $date = $data['request_date'];
            $snapshot = $user->attendanceRecords()->whereDate('attendance_date', $date)->first()?->schedule_snapshot ?? $schedules->snapshot($user, $date);
            if (! $schedules->approvedEnd($snapshot, $date, $data['request_time'], $data['type'])) {
                throw ValidationException::withMessages(['request_time' => 'Use your assigned shift date and a valid clock-out time. Overtime must end after the shift; undertime must end within it.']);
            }
            foreach ([OvertimeRequest::class, UndertimeRequest::class] as $model) {
                if ($model::where('user_id', $user->id)->whereDate('request_date', $date)->whereIn('status', ['pending', 'needs_review', 'approved'])->exists()) {
                    throw ValidationException::withMessages(['request_date' => 'You already have a pending or approved time request for this shift.']);
                }
            }
            if (LeaveRequest::where('user_id', $user->id)->where('status', 'approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
                throw ValidationException::withMessages(['request_date' => 'This shift has approved leave.']);
            }
            $model = $data['type'] === 'overtime' ? OvertimeRequest::class : UndertimeRequest::class;
            $model::create(['user_id' => $user->id, 'request_date' => $date, 'request_time' => $data['request_time'], 'reason' => $data['reason'], 'status' => 'needs_review']);
        });

        return to_route('personal.requests', ['type' => $data['type']])->with('status', 'Your request was submitted for admin approval.');
    }
}
