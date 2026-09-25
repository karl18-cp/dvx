<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\TeamLeaderWorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeLeaveController extends Controller
{
    private function actor(Request $request): User
    {
        $user = $request->user()->fresh();
        abort_unless($user->status === 'active' && in_array($user->role, ['agent', 'team_leader'], true), 403);

        return $user;
    }

    public function index(Request $request, TeamLeaderWorkspaceService $workspace)
    {
        $user = $this->actor($request);
        $data = $request->validate(['scope' => ['nullable', Rule::in(['mine', 'team'])], 'status' => ['nullable', Rule::in(['all', 'needs_review', 'approved', 'rejected'])]]);
        $scope = $data['scope'] ?? 'mine';
        abort_if($scope === 'team' && $user->role !== 'team_leader', 403);
        $query = LeaveRequest::with('user:id,name,username');
        if ($scope === 'team') {
            $query->whereIn('user_id', $workspace->members($user)->where('role', 'agent')->select('users.id'))->where('user_id', '!=', $user->id);
        } else {
            $query->where('user_id', $user->id);
        }
        $status = $data['status'] ?? 'all';
        $query->when($status !== 'all', fn ($q) => $q->whereIn('status', $status === 'needs_review' ? ['needs_review', 'pending'] : [$status]));

        return Inertia::render('leave-requests', ['isLeader' => $user->role === 'team_leader', 'scope' => $scope, 'filterStatus' => $status, 'today' => now('Asia/Manila')->toDateString(), 'statusMessage' => $request->session()->get('status'), 'requests' => $query->latest('id')->paginate(15)->withQueryString()->through(fn ($leave) => [
            'id' => $leave->id, 'employee' => $leave->user->name, 'employeeId' => $leave->user->username, 'startDate' => $leave->start_date->toDateString(), 'endDate' => $leave->end_date->toDateString(), 'days' => $leave->number_of_days, 'type' => $leave->leave_type, 'reason' => $leave->reason, 'status' => $leave->status, 'leaderStatus' => $leave->leader_status, 'requiresLeader' => $leave->requires_leader_approval, 'leaderName' => $leave->leader_name, 'leaderNotes' => $leave->leader_notes, 'adminNotes' => $leave->review_notes, 'paid' => $leave->isPaid(), 'canReview' => $scope === 'team' && $leave->requires_leader_approval && $leave->leader_status === 'pending' && in_array($leave->status, ['needs_review', 'pending'], true),
        ])]);
    }

    public function store(Request $request)
    {
        $user = $this->actor($request);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'leave_type' => ['required', Rule::in(['Vacation', 'Sick', 'Emergency', 'Other'])], 'reason' => ['required', 'string', 'max:2000']]);
        $days = (int) CarbonImmutable::parse($data['start_date'])->diffInDays(CarbonImmutable::parse($data['end_date'])) + 1;
        if ($days > 366) {
            throw ValidationException::withMessages(['end_date' => 'Leave cannot exceed 366 calendar days per request.']);
        }
        DB::transaction(function () use ($user, $data, $days) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($existing = LeaveRequest::where('request_id', $data['request_id'])->first()) {
                abort_unless($existing->user_id === $user->id, 403);

                return;
            }
            if (LeaveRequest::where('user_id', $user->id)->whereIn('status', ['needs_review', 'pending', 'approved'])->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists()) {
                throw ValidationException::withMessages(['start_date' => 'These dates overlap an existing pending or approved leave request.']);
            }
            $leave = new LeaveRequest;
            $leave->forceFill([...$data, 'user_id' => $user->id, 'number_of_days' => $days, 'status' => 'needs_review', 'is_paid' => false, 'requires_leader_approval' => $user->role === 'agent', 'leader_status' => $user->role === 'agent' ? 'pending' : 'not_required'])->save();
        });

        return to_route('leave-requests')->with('status', $user->role === 'agent' ? 'Leave submitted for team-leader approval, then final admin approval.' : 'Leave submitted for admin approval.');
    }

    public function review(Request $request, LeaveRequest $leave, TeamLeaderWorkspaceService $workspace)
    {
        $user = $this->actor($request);
        abort_unless($user->role === 'team_leader' && $leave->user_id !== $user->id && $workspace->members($user)->where('role', 'agent')->whereKey($leave->user_id)->exists(), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'notes' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000']]);
        DB::transaction(function () use ($user, $leave, $data) {
            User::whereKey($leave->user_id)->lockForUpdate()->firstOrFail();
            $leave->refresh();
            if (! $leave->requires_leader_approval || $leave->leader_status !== 'pending' || ! in_array($leave->status, ['needs_review', 'pending'], true)) {
                throw ValidationException::withMessages(['decision' => 'This request has already been reviewed. Refresh the list.']);
            }
            $leave->forceFill(['leader_status' => $data['decision'], 'leader_reviewed_by' => $user->id, 'leader_name' => $user->name, 'leader_reviewed_at' => now(), 'leader_notes' => $data['notes'] ?? null, 'status' => $data['decision'] === 'rejected' ? 'rejected' : 'needs_review'])->save();
        });

        return back()->with('status', $data['decision'] === 'approved' ? 'Initial approval saved. Awaiting final admin approval.' : 'Leave request rejected at initial review.');
    }
}
