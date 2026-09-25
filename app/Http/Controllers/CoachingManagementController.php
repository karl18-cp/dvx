<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentSkill;
use App\Models\CallEvaluation;
use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\Team;
use App\Models\TrainingLibraryMaterial;
use App\Models\User;
use App\Services\AssessmentNotificationService;
use App\Services\QaAccessService;
use App\Support\CoachingOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CoachingManagementController extends Controller
{
    public function index(Request $request, QaAccessService $access): Response
    {
        $filters = $request->only(['employee', 'team', 'campaign', 'coach', 'skill', 'status', 'from', 'to']);
        $query = CoachingRecord::query()->with(['employee:id,name,username', 'coach:id,name', 'skill:id,name', 'assessment:id,title', 'callEvaluation:id,percentage,finalized_at']);
        $access->scope($query, $request->user());
        $query->when($filters['employee'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['campaign'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v))
            ->when($filters['coach'] ?? null, fn ($q, $v) => $q->where('coach_id', $v))
            ->when($filters['skill'] ?? null, fn ($q, $v) => $q->where('skill_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['team'] ?? null, fn ($q, $v) => $q->where('team_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('coaching_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('coaching_date', '<=', $v));

        $base = $access->scope(CoachingRecord::query(), $request->user());

        return Inertia::render('coaching/index', [
            'records' => $query->latest('coaching_date')->paginate(15)->withQueryString(),
            'summary' => [
                'open' => (clone $base)->where('status', 'open')->count(),
                'follow_up_required' => (clone $base)->where('status', 'follow_up_required')->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
                'upcoming' => (clone $base)->whereNot('status', 'completed')->whereBetween('follow_up_date', [today(), today()->addDays(7)])->count(),
            ],
            'filters' => $filters,
            'employees' => User::query()->whereIn('role', ['agent', 'team_leader'])->where('status', 'active')->when($request->user()->role === 'team_leader', fn ($q) => $q->whereHas('teamMembership', fn ($m) => $m->whereIn('team_id', $access->teamIds($request->user()))))->orderBy('name')->get(['id', 'name', 'username']),
            'coaches' => User::query()->whereIn('role', ['admin', 'manager'])->when($request->user()->role === 'team_leader', fn ($q) => $q->whereIn('id', (clone $base)->select('coach_id')))->orderBy('name')->get(['id', 'name']),
            'skills' => AssessmentSkill::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'assessments' => Assessment::query()->when($request->user()->role === 'team_leader', fn ($q) => $q->whereIn('id', (clone $base)->select('assessment_id')))->orderBy('title')->get(['id', 'title']),
            'materials' => $request->user()->role === 'team_leader' ? [] : TrainingLibraryMaterial::query()->where('status', 'active')->orderBy('title')->get(['id', 'title', 'skill_id', 'type']),
            'types' => CoachingOptions::TYPES,
            'statuses' => CoachingOptions::STATUSES,
            'campaigns' => Campaign::query()->when($request->user()->role === 'team_leader', fn ($q) => $q->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $access->teamIds($request->user()))))->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'abbreviation', 'is_active']),
            'teams' => Team::query()->when($request->user()->role === 'team_leader', fn ($q) => $q->whereIn('id', $access->teamIds($request->user())))->orderBy('name')->get(['id', 'name', 'campaign_id']),
            'prefill' => in_array($request->user()->role, ['admin', 'manager'], true) ? $this->coachingPrefill($request) : [],
            'can_manage' => in_array($request->user()->role, ['admin', 'manager'], true),
        ]);
    }

    public function store(Request $request, AssessmentNotificationService $notifications): RedirectResponse
    {
        $data = $this->validated($request, true);
        if ($data['call_evaluation_id'] ?? null) {
            $source = CallEvaluation::query()->where('status', 'submitted')->findOrFail($data['call_evaluation_id']);
            throw_if((int) $data['employee_id'] !== $source->employee_id, ValidationException::withMessages(['call_evaluation_id' => 'The source Evaluation belongs to another employee.']));
            if ($existing = CoachingRecord::query()->where('call_evaluation_id', $source->id)->first()) {
                return to_route('coaching.manage.show', $existing)->with('status', 'Coaching already exists for this Evaluation.');
            }
        }
        $employee = User::query()->with('teamMembership.team.campaign:id,name')->findOrFail($data['employee_id']);
        $team = $employee->teamMembership?->team;
        $campaign = $team?->campaign;
        abort_unless($team && $campaign, 422, 'The employee must belong to a Campaign team before coaching can be created.');
        $record = DB::transaction(function () use ($data, $request, $campaign, $team) {
            $materialIds = $data['material_ids'] ?? [];
            unset($data['material_ids']);
            $record = CoachingRecord::query()->create([...$data, 'campaign_id' => $campaign?->id, 'campaign_name' => $campaign?->name, 'team_id' => $team?->id, 'team_name' => $team?->name, 'coach_id' => $request->user()->id, 'status' => 'open']);
            foreach ($materialIds as $materialId) {
                $record->trainingAssignments()->create(['library_material_id' => $materialId, 'is_required' => true, 'assigned_by' => $request->user()->id]);
            }
            $action = $record->call_evaluation_id ? 'Coaching Created from Evaluation' : 'Coaching Record Created';
            $this->log($request->user()->id, $action, $record, ['training_count' => count($materialIds), 'call_evaluation_id' => $record->call_evaluation_id]);

            return $record;
        });
        $notifications->createForUser($employee, "coaching:{$record->id}:assigned", 'coaching_assigned', 'New Coaching Assigned', 'A new coaching record is ready for your review.', route('coaching.my.show', $record));
        if ($record->trainingAssignments()->exists()) {
            $notifications->createForUser($employee, "coaching:{$record->id}:training:initial", 'coaching_training_assigned', 'Training Assigned', 'Training materials were assigned as part of your coaching action plan.', route('coaching.my.show', $record));
        }

        return to_route('coaching.manage.show', $record)->with('status', 'Coaching record created.');
    }

    public function show(Request $request, CoachingRecord $coaching, QaAccessService $access): Response
    {
        $access->authorizeTeam($request->user(), $coaching->team_id, $coaching->employee_id);

        return Inertia::render('coaching/show', ['coaching' => $this->detail($coaching), 'materials' => $request->user()->role === 'team_leader' ? [] : TrainingLibraryMaterial::query()->where('status', 'active')->orderBy('title')->get(['id', 'title', 'type', 'skill_id']), 'can_manage' => in_array($request->user()->role, ['admin', 'manager'], true)]);
    }

    public function update(Request $request, CoachingRecord $coaching): RedirectResponse
    {
        abort_if($coaching->status === 'completed', 422, 'Completed coaching records are read-only.');
        $data = $this->validated($request, false);
        unset($data['material_ids']);
        $coaching->update($data);
        $this->log($request->user()->id, 'Coaching Record Updated', $coaching);

        return back()->with('status', 'Coaching record updated.');
    }

    public function assignTraining(Request $request, CoachingRecord $coaching, AssessmentNotificationService $notifications): RedirectResponse
    {
        abort_if($coaching->status === 'completed', 422, 'Completed coaching records are read-only.');
        $data = $request->validate(['material_ids' => ['required', 'array', 'min:1'], 'material_ids.*' => ['integer', 'distinct', Rule::exists('training_library_materials', 'id')->where('status', 'active')]]);
        DB::transaction(function () use ($data, $request, $coaching): void {
            foreach ($data['material_ids'] as $id) {
                $coaching->trainingAssignments()->firstOrCreate(['library_material_id' => $id], ['is_required' => true, 'assigned_by' => $request->user()->id]);
            }
            $this->log($request->user()->id, 'Training Assigned from Coaching', $coaching, ['material_ids' => $data['material_ids']]);
        });
        $notificationKey = sha1(implode(',', $data['material_ids']));
        $notifications->createForUser($coaching->employee, "coaching:{$coaching->id}:training:{$notificationKey}", 'coaching_training_assigned', 'Training Assigned', 'Training materials were assigned as part of your coaching action plan.', route('coaching.my.show', $coaching));

        return back()->with('status', 'Training assigned.');
    }

    public function complete(Request $request, CoachingRecord $coaching): RedirectResponse
    {
        abort_if($coaching->status === 'completed', 422, 'Coaching is already completed.');
        $incomplete = $coaching->trainingAssignments()->where('is_required', true)->whereDoesntHave('progress', fn ($q) => $q->where('employee_id', $coaching->employee_id)->whereNotNull('completed_at'))->exists();
        if ($incomplete) {
            throw ValidationException::withMessages(['completion' => 'Required coaching training must be completed first.']);
        }
        DB::transaction(function () use ($request, $coaching): void {
            $coaching->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $request->user()->id]);
            $this->log($request->user()->id, 'Coaching Completed', $coaching);
        });

        return back()->with('status', 'Coaching completed.');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'employee_id' => [$creating ? 'required' : 'sometimes', 'integer', Rule::exists('users', 'id')->whereIn('role', ['agent', 'team_leader'])],
            'type' => ['required', Rule::in(CoachingOptions::TYPES)],
            'skill_id' => ['nullable', 'integer', 'exists:assessment_skills,id'],
            'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'call_evaluation_id' => ['nullable', 'integer', Rule::exists('call_evaluations', 'id')->where('status', 'submitted')],
            'coaching_date' => ['required', 'date'],
            'summary' => ['required', 'string', 'max:5000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'areas_for_improvement' => ['nullable', 'string', 'max:5000'],
            'action_plan' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:coaching_date'],
            'status' => ['sometimes', Rule::in(['open', 'follow_up_required'])],
            'material_ids' => ['sometimes', 'array'],
            'material_ids.*' => ['integer', 'distinct', Rule::exists('training_library_materials', 'id')->where('status', 'active')],
        ]);
    }

    private function coachingPrefill(Request $request): array
    {
        $prefill = $request->only(['employee', 'skill', 'assessment', 'create', 'call_evaluation']);
        if ($request->filled('call_evaluation')) {
            $evaluation = CallEvaluation::query()->where('status', 'submitted')->findOrFail($request->integer('call_evaluation'));
            $prefill = [...$prefill, 'employee' => (string) $evaluation->employee_id, 'call_evaluation_id' => (string) $evaluation->id, 'summary' => "QA Evaluation: {$evaluation->scorecard_name} ({$evaluation->percentage}%)", 'strengths' => $evaluation->strengths ?? '', 'areas_for_improvement' => $evaluation->areas_for_improvement ?? '', 'action_plan' => $evaluation->recommended_action ?? $evaluation->overall_feedback ?? ''];
        }

        return $prefill;
    }

    private function detail(CoachingRecord $coaching): CoachingRecord
    {
        return $coaching->load(['employee:id,name,username', 'coach:id,name', 'skill:id,name', 'assessment:id,title', 'callEvaluation:id,percentage,finalized_at,scorecard_name', 'trainingAssignments.material.skill:id,name', 'trainingAssignments.progress' => fn ($q) => $q->where('employee_id', $coaching->employee_id)]);
    }

    private function log(int $actor, string $action, CoachingRecord $record, array $metadata = []): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $actor, 'action' => $action, 'target_type' => CoachingRecord::class, 'target_id' => $record->id, 'metadata' => $metadata ? json_encode($metadata) : null, 'created_at' => now()]);
    }
}
