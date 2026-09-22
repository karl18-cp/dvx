<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use App\Services\AssessmentNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentAssignmentController extends Controller
{
    private const ASSIGNMENT_TIMEZONE = 'Asia/Manila';

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'assessment_id' => ['nullable', 'integer'], 'employee' => ['nullable', 'string', 'max:100'],
            'campaign_id' => ['nullable', 'integer'], 'team_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['assigned', 'in_progress', 'pending_review', 'passed', 'failed'])],
            'assigned_from' => ['nullable', 'date_format:Y-m-d'], 'assigned_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('assigned_from'), 'after_or_equal:assigned_from')],
            'due_from' => ['nullable', 'date_format:Y-m-d'], 'due_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('due_from'), 'after_or_equal:due_from')],
        ]);
        $assignments = AssessmentAssignment::query()->select(['id', 'assessment_id', 'employee_id', 'source_team_id', 'campaign_id', 'campaign_name', 'assigned_at', 'available_from', 'due_date', 'status'])
            ->withCount('attempts')
            ->selectSub(function ($query): void {
                $query->from('assessment_training_progress as progress')
                    ->join('assessment_training_materials as material', 'material.id', '=', 'progress.material_id')
                    ->whereColumn('progress.employee_id', 'assessment_assignments.employee_id')
                    ->whereColumn('material.assessment_id', 'assessment_assignments.assessment_id')
                    ->selectRaw('count(*)');
            }, 'training_progress_count')
            ->with(['assessment:id,title,available_at,due_at', 'employee:id,name,username', 'team:id,name'])
            ->when($filters['assessment_id'] ?? null, fn ($q, $v) => $q->where('assessment_id', $v))
            ->when($filters['campaign_id'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v))
            ->when($filters['team_id'] ?? null, fn ($q, $v) => $q->where('source_team_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['employee'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($u) => $u->where(fn ($x) => $x->where('name', 'like', '%'.addcslashes($v, '%_\\').'%')->orWhere('username', 'like', '%'.addcslashes($v, '%_\\').'%'))))
            ->when($filters['assigned_from'] ?? null, fn ($q, $v) => $q->where('assigned_at', '>=', $this->toUtc($v)))
            ->when($filters['assigned_to'] ?? null, fn ($q, $v) => $q->where('assigned_at', '<', $this->toUtc($v)->addDay()))
            ->when($filters['due_from'] ?? null, fn ($q, $v) => $q->whereRaw('COALESCE(due_date, (SELECT due_at FROM assessments WHERE assessments.id = assessment_assignments.assessment_id)) >= ?', [$this->toUtc($v)]))
            ->when($filters['due_to'] ?? null, fn ($q, $v) => $q->whereRaw('COALESCE(due_date, (SELECT due_at FROM assessments WHERE assessments.id = assessment_assignments.assessment_id)) < ?', [$this->toUtc($v)->addDay()]))
            ->latest('assigned_at')->paginate(25)->withQueryString();

        return Inertia::render('assessments/assignments', [
            'assignments' => $assignments, 'filters' => $filters,
            'assessments' => Assessment::query()->where('status', 'published')->orderBy('title')->get(['id', 'title', 'available_at', 'due_at']),
            'assessmentFilters' => Assessment::query()->whereHas('assignments')->orderBy('title')->get(['id', 'title']),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->withCount('members')->orderBy('name')->get(['id', 'name', 'campaign_id']),
        ]);
    }

    public function employees(Request $request): array
    {
        $search = trim($request->string('search')->toString());

        return User::query()->where('status', 'active')->whereIn('role', ['agent', 'team_leader'])->when($search !== '', fn ($q) => $q->where(fn ($x) => $x->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('username', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'username'])->all();
    }

    public function countRecipients(Request $request): array
    {
        $data = $request->validate(['employee_ids' => ['array'], 'employee_ids.*' => ['integer'], 'team_ids' => ['array'], 'team_ids.*' => ['integer'], 'all_employees' => ['required', 'boolean']]);
        $ids = collect($data['employee_ids'] ?? [])->map(fn ($id) => (int) $id);
        if ($data['all_employees']) {
            $ids = $ids->merge(User::query()->where('status', 'active')->whereIn('role', ['agent', 'team_leader'])->pluck('id'));
        }
        if ($data['team_ids'] ?? []) {
            $ids = $ids->merge(DB::table('team_members')->whereIn('team_id', $data['team_ids'])->pluck('user_id'));
        }

        return ['count' => $ids->unique()->count()];
    }

    public function store(Request $request, AssessmentNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'assessment_id' => ['required', 'integer', Rule::exists('assessments', 'id')->where('status', 'published')],
            'employee_ids' => ['array'], 'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('status', 'active')],
            'team_ids' => ['array'], 'team_ids.*' => ['integer', 'distinct', Rule::exists('teams', 'id')], 'all_employees' => ['required', 'boolean'],
            'available_from' => ['nullable', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:available_from'],
        ]);
        $assessment = Assessment::query()->where('status', 'published')->findOrFail($data['assessment_id']);
        $employees = collect($data['employee_ids'] ?? [])->mapWithKeys(fn ($id) => [(int) $id => null]);
        if ($data['all_employees']) {
            User::query()->where('status', 'active')->whereIn('role', ['agent', 'team_leader'])->orderBy('id')->pluck('id')->each(fn ($id) => $employees->put($id, null));
        }
        if ($data['team_ids'] ?? []) {
            DB::table('team_members')->whereIn('team_id', $data['team_ids'])->orderBy('id')->get(['user_id', 'team_id'])->each(fn ($row) => $employees->put($row->user_id, $row->team_id));
        }
        abort_if($employees->isEmpty(), 422, 'Select at least one eligible employee or team.');
        $available = isset($data['available_from']) ? $this->toUtc($data['available_from']) : $assessment->available_at;
        $due = isset($data['due_date']) ? $this->toUtc($data['due_date']) : $assessment->due_at;
        abort_if($available && $due && $due < $available, 422, 'Due date cannot be earlier than available date.');
        $now = now();
        $campaigns = User::query()->whereKey($employees->keys())->with('teamMembership.team.campaign:id,name')->get()->keyBy('id')->map(fn ($employee) => $employee->teamMembership?->team?->campaign);
        if (! $assessment->applies_to_all_campaigns) {
            $allowedCampaigns = $assessment->campaigns()->pluck('campaigns.id');
            abort_if($campaigns->contains(fn ($campaign) => ! $campaign || ! $allowedCampaigns->contains($campaign->id)), 422, 'One or more selected employees are outside this assessment campaign scope.');
        }
        DB::transaction(function () use ($employees, $campaigns, $assessment, $request, $available, $due, $now, $notifications): void {
            $rows = $employees->map(fn ($team, $employee) => ['assessment_id' => $assessment->id, 'employee_id' => $employee, 'campaign_id' => $campaigns->get($employee)?->id, 'campaign_name' => $campaigns->get($employee)?->name, 'source_team_id' => $team, 'assigned_by' => $request->user()->id, 'assigned_at' => $now, 'available_from' => $available, 'due_date' => $due, 'status' => 'assigned', 'created_at' => $now, 'updated_at' => $now])->values()->all();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('assessment_assignments')->insertOrIgnore($chunk);
            }
            AssessmentAssignment::query()->where('assessment_id', $assessment->id)->whereIn('employee_id', $employees->keys())->get()->each(function ($assignment) use ($notifications, $assessment, $available, $due): void {
                $message = $assessment->title.($available ? ' is available '.$available->copy()->setTimezone(self::ASSIGNMENT_TIMEZONE)->format('M j, Y g:i A') : ' is available now').($due ? ' and due '.$due->copy()->setTimezone(self::ASSIGNMENT_TIMEZONE)->format('M j, Y g:i A').'.' : '.');
                $notifications->create($assignment, 'assigned', 'New Assessment Assigned', $message);
            });
            DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'assessment.assigned', 'target_type' => Assessment::class, 'target_id' => $assessment->id, 'metadata' => json_encode(['requested_employees' => count($rows)], JSON_THROW_ON_ERROR), 'created_at' => $now]);
        });

        return back()->with('status', 'Assessment assigned to '.$employees->count().' unique employee(s).');
    }

    public function extendDeadlines(Request $request): RedirectResponse
    {
        $data = $request->validate(['assignment_ids' => ['required', 'array', 'min:1'], 'assignment_ids.*' => ['integer', 'distinct', Rule::exists('assessment_assignments', 'id')], 'due_date' => ['required', 'date']]);
        $due = $this->toUtc($data['due_date']);
        $assignments = AssessmentAssignment::query()->whereIn('id', $data['assignment_ids'])->whereNotIn('status', ['passed', 'failed', 'pending_review'])->get();
        DB::transaction(function () use ($assignments, $due, $request): void {
            foreach ($assignments as $assignment) {
                abort_if($assignment->effectiveAvailableAt()?->gt($due), 422, 'Due date cannot be earlier than availability.');
                $assignment->update(['due_date' => $due]);
                DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'Bulk Deadline Extended', 'target_type' => AssessmentAssignment::class, 'target_id' => $assignment->id, 'metadata' => json_encode(['due_date' => $due->toIso8601String()]), 'created_at' => now()]);
            }
        });

        return back()->with('status', "Deadline extended for {$assignments->count()} assignment(s).");
    }

    public function update(Request $request, AssessmentAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'available_from' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:available_from'],
        ]);
        $before = $assignment->only(['available_from', 'due_date']);
        $available = isset($data['available_from']) ? $this->toUtc($data['available_from']) : null;
        $due = isset($data['due_date']) ? $this->toUtc($data['due_date']) : null;

        DB::transaction(function () use ($assignment, $available, $due, $before, $request): void {
            $assignment->update([
                'available_from' => $available,
                'due_date' => $due,
            ]);
            $changes = collect($assignment->only(['available_from', 'due_date']))
                ->filter(fn ($value, $field) => (string) $value !== (string) $before[$field])
                ->keys()->values()->all();
            DB::table('assessment_activity_logs')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'Assessment Assignment Updated',
                'target_type' => AssessmentAssignment::class,
                'target_id' => $assignment->id,
                'metadata' => json_encode(['assessment_id' => $assignment->assessment_id, 'employee_id' => $assignment->employee_id, 'changed_fields' => $changes], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });

        return back()->with('status', 'Assignment updated successfully.');
    }

    public function destroy(Request $request, AssessmentAssignment $assignment): RedirectResponse
    {
        abort_if($this->hasHistory($assignment), 422, 'Cannot delete an assignment that already has employee activity. Historical assessment records must be preserved.');

        DB::transaction(function () use ($assignment, $request): void {
            DB::table('assessment_activity_logs')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'Assessment Assignment Deleted',
                'target_type' => AssessmentAssignment::class,
                'target_id' => $assignment->id,
                'metadata' => json_encode(['assessment_id' => $assignment->assessment_id, 'employee_id' => $assignment->employee_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            $assignment->delete();
        });

        return back()->with('status', 'Assignment removed successfully.');
    }

    private function hasHistory(AssessmentAssignment $assignment): bool
    {
        return $assignment->attempts()->exists() || DB::table('assessment_training_progress as progress')
            ->join('assessment_training_materials as material', 'material.id', '=', 'progress.material_id')
            ->where('progress.employee_id', $assignment->employee_id)
            ->where('material.assessment_id', $assignment->assessment_id)
            ->exists();
    }

    private function toUtc(string $value): Carbon
    {
        return Carbon::parse($value, self::ASSIGNMENT_TIMEZONE)->utc();
    }
}
