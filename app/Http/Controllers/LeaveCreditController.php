<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AssessmentNotificationService;
use App\Services\AuditLogService;
use App\Services\LeaveCreditService;
use App\Services\TeamLeaderWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LeaveCreditController extends Controller
{
    public function __invoke(Request $request, LeaveCreditService $credits, TeamLeaderWorkspaceService $workspace)
    {
        $actor = $request->user()->fresh();
        abort_unless($actor->canAccessAccount() && in_array($actor->role, ['admin', 'accounting', 'team_leader', 'agent'], true), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'eligibility' => ['nullable', Rule::in(['all', 'eligible', 'upcoming'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'employee' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = User::query()
            ->whereIn('role', ['agent', 'team_leader'])
            ->with(['personalInformation:id,user_id,start_date', 'leaveRequests:id,user_id,start_date,end_date,status,is_paid,leave_type'])
            ->orderBy('name');
        if ($actor->role === 'agent') {
            $query->whereKey($actor->id);
        } elseif ($actor->role === 'team_leader') {
            $memberIds = $workspace->members($actor)->where('role', 'agent')->select('users.id');
            $query->where(fn ($scope) => $scope->whereKey($actor->id)->orWhereIn('id', $memberIds));
        }
        $query->when($filters['search'] ?? null, fn ($employees, $search) => $employees->where(fn ($names) => $names
            ->where('name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%')));

        $today = now('Asia/Manila')->toDateString();
        $records = $query->get()->map(function (User $employee) use ($credits, $today) {
            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'username' => $employee->username,
                'role' => $employee->role,
                'status' => $employee->status,
                'avatar' => $employee->avatar,
                ...$credits->summary($employee, $today),
            ];
        });
        $eligibility = $filters['eligibility'] ?? 'all';
        $records = $records->when($eligibility === 'eligible', fn ($rows) => $rows->where('eligible', true))
            ->when($eligibility === 'upcoming', fn ($rows) => $rows->where('eligible', false));
        $page = (int) ($filters['page'] ?? 1);
        $perPage = 20;
        $paginator = new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $selectedId = (int) ($filters['employee'] ?? ($actor->role === 'agent' ? $actor->id : 0));
        $selected = $selectedId ? (clone $query)->whereKey($selectedId)->first() : null;

        return Inertia::render('leave-credits', [
            'today' => $today,
            'canViewAll' => in_array($actor->role, ['admin', 'accounting'], true),
            'canManage' => $actor->hasPermission('leave_credits.manage'),
            'filters' => ['search' => $filters['search'] ?? '', 'eligibility' => $eligibility],
            'summary' => [
                'employees' => $records->count(),
                'eligible' => $records->where('eligible', true)->count(),
                'available' => $records->sum('available_credits'),
                'used' => $records->sum('used_credits'),
            ],
            'records' => $paginator,
            'ledger' => $selected ? [
                'employee' => ['id' => $selected->id, 'name' => $selected->name, 'username' => $selected->username, 'avatar' => $selected->avatar],
                ...$credits->ledger($selected, $today),
            ] : null,
        ]);
    }

    public function store(Request $request, User $employee, LeaveCreditService $credits, TeamLeaderWorkspaceService $workspace, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        $actor = $request->user()->fresh();
        abort_unless($actor->hasPermission('leave_credits.manage'), 403);
        abort_unless(in_array($employee->role, ['agent', 'team_leader'], true), 404);

        $data = $request->validate([
            'type' => ['required', Rule::in(LeaveCreditService::MANUAL_TYPES)],
            'amount' => ['required', 'numeric', 'between:-1000,1000', 'not_in:0'],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $entry = $credits->post($employee, $actor, $data);
        } catch (HttpException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        $label = str($entry->type)->replace('_', ' ')->title();
        $audit->record($actor, 'approvals', 'Leave credit '.$label, $entry, metadata: ['employee_id' => $employee->id, 'type' => $entry->type, 'amount' => $entry->amount, 'effective_date' => $entry->effective_date->toDateString(), 'reference' => $entry->reference, 'notes' => $entry->notes], subjectId: $employee->id);
        $notifications->createForUser($employee, "leave-credit:{$entry->id}", 'leave_credit', 'Leave credit ledger updated', "{$label}: {$entry->amount} day(s). {$entry->notes}", '/leave-credits?employee='.$employee->id);

        return back()->with('success', 'Leave credit transaction recorded.');
    }
}
