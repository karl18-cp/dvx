<?php

namespace App\Services;

use App\Models\CallEvaluation;
use App\Models\CallEvaluationScorecard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CallEvaluationSnapshotService
{
    public function createEvaluation(CallEvaluationScorecard $scorecard, User $employee, User $evaluator, array $attributes): CallEvaluation
    {
        throw_if($employee->status !== 'active' || ! in_array($employee->role, ['agent', 'team_leader'], true), ValidationException::withMessages(['employee_id' => 'Choose an active agent or team leader.']));
        $teams = app(CallEvaluationEmployeeTeams::class)->forEmployee($employee);
        $selectedTeamId = $attributes['team_id'] ?? null;
        $team = $selectedTeamId ? $teams->firstWhere('id', (int) $selectedTeamId) : ($teams->count() === 1 ? $teams->first() : null);
        throw_if($teams->isNotEmpty() && ! $team, ValidationException::withMessages(['team_id' => 'Choose one of this employee’s assigned teams.']));
        $campaign = $team?->campaign;
        throw_if(! $team || ! $campaign, ValidationException::withMessages(['employee_id' => 'The employee must belong to a Campaign team.']));

        $scorecard->loadMissing(['campaigns:id', 'categories.criteria.skill:id,name']);
        throw_if($scorecard->status !== 'active', ValidationException::withMessages(['scorecard_id' => 'Only active Scorecards can start an Evaluation.']));
        throw_if(! $scorecard->applies_to_all_campaigns && ! $scorecard->campaigns->contains('id', $campaign->id), ValidationException::withMessages(['scorecard_id' => 'The Scorecard is not compatible with the employee Campaign.']));

        $snapshot = $this->snapshot($scorecard);

        return DB::transaction(fn () => CallEvaluation::query()->create([
            ...$attributes,
            'employee_id' => $employee->id,
            'evaluator_id' => $evaluator->id,
            'scorecard_id' => $scorecard->id,
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'team_id' => $team->id,
            'team_name' => $team->name,
            'scorecard_name' => $scorecard->name,
            'scorecard_snapshot' => $snapshot,
            'status' => 'draft',
        ]));
    }

    public function snapshot(CallEvaluationScorecard $scorecard): array
    {
        $scorecard->loadMissing(['campaigns:id,name', 'categories.criteria.skill:id,name']);
        $categories = $scorecard->categories->map(fn ($category) => [
            'source_category_id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'display_order' => $category->display_order,
            'criteria' => $category->criteria->map(fn ($criterion) => [
                'key' => 'criterion-'.$criterion->id,
                'source_criterion_id' => $criterion->id,
                'skill_id' => $criterion->skill_id,
                'skill_name' => $criterion->skill?->name,
                'label' => $criterion->label,
                'guidance' => $criterion->guidance,
                'points_possible' => (float) $criterion->points_possible,
                'is_required' => $criterion->is_required,
                'is_critical' => $criterion->is_critical,
                'allows_na' => $criterion->allows_na,
                'display_order' => $criterion->display_order,
            ])->values()->all(),
        ])->values();

        throw_if($categories->isEmpty() || $categories->sum(fn ($category) => count($category['criteria'])) === 0, ValidationException::withMessages(['scorecard_id' => 'The Scorecard must contain at least one Category and Criterion.']));
        throw_if($categories->flatMap(fn ($category) => $category['criteria'])->sum('points_possible') <= 0, ValidationException::withMessages(['scorecard_id' => 'The Scorecard must have a positive possible score.']));

        return [
            'version' => 1,
            'source_scorecard_id' => $scorecard->id,
            'name' => $scorecard->name,
            'description' => $scorecard->description,
            'passing_score' => (float) $scorecard->passing_score,
            'applies_to_all_campaigns' => $scorecard->applies_to_all_campaigns,
            'campaigns' => $scorecard->campaigns->map(fn ($campaign) => ['id' => $campaign->id, 'name' => $campaign->name])->values()->all(),
            'categories' => $categories->all(),
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
