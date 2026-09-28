<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTrainingPlanRequest;
use App\Models\Campaign;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\TrainingAllowanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TrainingPlanController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->status === 'active' && in_array($request->user()->role, ['admin', 'qa_admin'], true), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeManager($request);

        return Inertia::render('training-plans', [
            'plans' => TrainingPlan::with(['campaign:id,name', 'trainees:id,name,username,training_campaign_id,training_status'])->latest()->get(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name']),
            'trainees' => User::where('role', 'trainee')->where('status', 'active')->where('training_status', 'in_training')->orderBy('name')->get(['id', 'name', 'username', 'training_campaign_id']),
            'enrollments' => DB::table('training_plan_enrollments')->get(['user_id', 'training_plan_id']),
        ]);
    }

    public function store(SaveTrainingPlanRequest $request)
    {
        $this->save($request, new TrainingPlan);

        return back()->with('status', 'Training plan created.');
    }

    public function update(SaveTrainingPlanRequest $request, TrainingPlan $plan)
    {
        $this->save($request, $plan);

        return back()->with('status', 'Training plan updated. Allowance estimates have been recalculated.');
    }

    private function save(SaveTrainingPlanRequest $request, TrainingPlan $plan): void
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $plan, $data): void {
            $plan = $plan->exists ? TrainingPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail() : $plan;
            $before = $plan->exists ? $plan->load('trainees:id,name,username')->toArray() : null;
            $existingIds = $plan->exists ? $plan->trainees()->pluck('users.id')->all() : [];
            $selected = array_map('intval', $data['trainee_ids']);
            if ($plan->exists && $plan->start_date->toDateString() <= now('Asia/Manila')->toDateString() && array_diff($existingIds, $selected)) {
                throw ValidationException::withMessages(['trainee_ids' => 'Keep existing trainees on a started plan to preserve their allowance history.']);
            }
            $users = User::whereIn('id', $selected)->orderBy('id')->lockForUpdate()->get();
            foreach ($users as $user) {
                $alreadyAssigned = DB::table('training_plan_enrollments')->where('user_id', $user->id)->when($plan->exists, fn ($q) => $q->where('training_plan_id', '!=', $plan->id))->exists();
                $existing = in_array($user->id, $existingIds, true);
                if ($alreadyAssigned || (int) $user->training_campaign_id !== (int) $data['campaign_id'] || (! $existing && ($user->role !== 'trainee' || $user->status !== 'active' || $user->training_status !== 'in_training'))) {
                    throw ValidationException::withMessages(['trainee_ids' => 'Choose active trainees in this campaign who are not enrolled in another plan.']);
                }
            }
            $phases = array_map(function ($phase) {
                [$whole, $fraction] = array_pad(explode('.', (string) $phase['rate']), 2, '');

                return ['days' => (int) $phase['days'], 'rate_cents' => ((int) $whole * 100) + (int) str_pad($fraction, 2, '0')];
            }, $data['phases']);
            $plan->fill(collect($data)->except(['trainee_ids', 'phases'])->all());
            $plan->weekdays = array_map('intval', $data['weekdays']);
            $plan->phases = $phases;
            if (! $plan->exists) {
                $plan->created_by = $request->user()->id;
            }
            $plan->save();
            $plan->trainees()->sync($selected);
            DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => $before ? 'Training plan updated' : 'Training plan created', 'target_type' => TrainingPlan::class, 'target_id' => $plan->id, 'metadata' => json_encode(['before' => $before, 'after' => $plan->fresh()->load('trainees:id,name,username')->toArray()], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public function allowance(Request $request, TrainingPlan $plan, User $trainee, TrainingAllowanceService $service)
    {
        $this->authorizeManager($request);
        abort_unless($plan->trainees()->whereKey($trainee->id)->exists(), 404);

        return response()->json($service->summary($plan, $trainee));
    }

    public function mine(Request $request, TrainingAllowanceService $service)
    {
        $user = $request->user()->fresh();
        abort_unless($user->status === 'active' && in_array($user->role, ['trainee', 'agent'], true), 403);
        $plan = TrainingPlan::with('campaign:id,name')->whereHas('trainees', fn ($q) => $q->where('users.id', $user->id))->first();

        return Inertia::render('my-training-plan', ['plan' => $plan, 'allowance' => $plan ? $service->summary($plan, $user) : null]);
    }
}
