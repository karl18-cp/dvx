<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use App\Services\AttendanceScheduleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RequestApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === 'admin', 403);

        return Inertia::render('requests', [
            'undertimeRequests' => UndertimeRequest::query()->with('user')->latest()->get()->map(fn (UndertimeRequest $item) => $this->timeRequestData($item)),
            'overtimeRequests' => OvertimeRequest::query()->with('user')->latest()->get()->map(fn (OvertimeRequest $item) => $this->timeRequestData($item)),
            'leaveRequests' => LeaveRequest::query()->with('user')->latest()->get()->map(fn (LeaveRequest $item): array => [
                ...$this->baseData($item, $item->user),
                'startDate' => $item->start_date->toDateString(),
                'endDate' => $item->end_date->toDateString(),
                'numberOfDays' => $item->number_of_days,
                'leaveType' => $item->leave_type,
                'isPaid' => $item->isPaid(),
                'reason' => $item->reason,
                'initialApproval' => $item->requires_leader_approval ? $item->leader_status : 'not_required',
                'leaderName' => $item->leader_name,
                'leaderNotes' => $item->leader_notes,
            ]),
        ]);
    }

    public function updateStatus(Request $request, string $type, int $requestId, AttendanceScheduleService $attendance): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'is_paid' => ['sometimes', 'boolean'],
        ]);

        $model = $this->requestModel($type)::query()->findOrFail($requestId);
        DB::transaction(function () use ($model, $type, $request, $data, $attendance): void {
            $employee = User::query()->lockForUpdate()->findOrFail($model->user_id);
            $model->refresh();
            if ($data['status'] === 'approved') {
                if ($model instanceof LeaveRequest) {
                    if ($model->requires_leader_approval && $model->leader_status !== 'approved') {
                        throw ValidationException::withMessages(['status' => 'The assigned team leader must initially approve this agent’s leave before final approval.']);
                    }
                    if ($model->end_date->lt($model->start_date)) {
                        throw ValidationException::withMessages(['status' => 'The leave end date must not precede its start date.']);
                    }
                    $overlap = LeaveRequest::query()->where('user_id', $employee->id)->where('id', '!=', $model->id)->where('status', 'approved')->whereDate('start_date', '<=', $model->end_date)->whereDate('end_date', '>=', $model->start_date)->exists();
                    if ($overlap) {
                        throw ValidationException::withMessages(['status' => 'This employee already has approved leave during these dates.']);
                    }
                } else {
                    $date = $model->request_date->toDateString();
                    $record = $employee->attendanceRecords()->whereDate('attendance_date', $date)->first();
                    $schedule = $record?->schedule_snapshot ?? $attendance->snapshot($employee, $date);
                    if (! $attendance->approvedEnd($schedule, $date, $model->request_time, $type)) {
                        throw ValidationException::withMessages(['status' => 'Assign a working schedule and check the requested clock-out time. Undertime must be within the shift; overtime must extend it and end within 24 hours of shift start.']);
                    }
                    $opposite = $type === 'overtime' ? UndertimeRequest::class : OvertimeRequest::class;
                    if ($opposite::query()->where('user_id', $employee->id)->whereDate('request_date', $date)->where('status', 'approved')->exists()
                        || LeaveRequest::query()->where('user_id', $employee->id)->where('status', 'approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
                        throw ValidationException::withMessages(['status' => 'This shift already has a conflicting approved request. Resolve that request first.']);
                    }
                }
            }
            $model->update([
                'status' => $data['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                ...($model instanceof LeaveRequest ? ['is_paid' => $data['is_paid'] ?? $model->isPaid()] : []),
            ]);
            $attendance->syncRequest($model);
        });

        return to_route('requests')->with('status', 'Employee request updated successfully.');
    }

    /** @return class-string<Model> */
    private function requestModel(string $type): string
    {
        return match ($type) {
            'undertime' => UndertimeRequest::class,
            'overtime' => OvertimeRequest::class,
            'leave' => LeaveRequest::class,
            default => abort(404),
        };
    }

    private function timeRequestData(UndertimeRequest|OvertimeRequest $item): array
    {
        return [
            ...$this->baseData($item, $item->user),
            'requestDate' => $item->request_date->toDateString(),
            'requestTime' => substr($item->request_time, 0, 5),
            'reason' => $item->reason,
        ];
    }

    private function baseData(Model $item, User $user): array
    {
        return [
            'id' => $item->getKey(),
            'employeeId' => $user->username,
            'requester' => $user->name,
            'role' => $this->positionLabel($user->role),
            'team' => $user->team,
            'status' => $item->getAttribute('status'),
            'submittedAt' => $item->getAttribute('created_at')?->toISOString(),
        ];
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
