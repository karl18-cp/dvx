<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                'reason' => $item->reason,
            ]),
        ]);
    }

    public function updateStatus(Request $request, string $type, int $requestId): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $model = $this->requestModel($type)::query()->findOrFail($requestId);
        $model->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

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
