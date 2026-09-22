<?php

namespace App\Services;

use App\Models\CallEvaluation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CallEvaluationScoringService
{
    public function calculate(CallEvaluation $evaluation, Collection $results, bool $forSubmission = false): array
    {
        $earned = 0.0;
        $possible = 0.0;
        $critical = false;
        $missing = [];
        $naCount = 0;
        $completed = 0;
        $categories = [];
        $skills = [];

        foreach ($evaluation->scorecard_snapshot['categories'] ?? [] as $category) {
            $categoryEarned = 0.0;
            $categoryPossible = 0.0;
            foreach ($category['criteria'] ?? [] as $criterion) {
                $result = $results->get($criterion['key']);
                $isNa = (bool) ($result?->is_na ?? false);
                $points = $result?->points_awarded;
                $max = (float) $criterion['points_possible'];
                if ($isNa) {
                    if (! $criterion['allows_na']) {
                        throw ValidationException::withMessages(['criteria' => "{$criterion['label']} does not allow N/A."]);
                    }
                    $naCount++;
                    $completed++;

                    continue;
                }
                if ($points === null) {
                    if ($criterion['is_required']) {
                        $missing[] = $criterion['label'];
                    }

                    continue;
                }
                $points = (float) $points;
                if ($points < 0 || $points > $max) {
                    throw ValidationException::withMessages(['criteria' => "{$criterion['label']} must be between 0 and {$max} points."]);
                }
                $completed++;
                $earned += $points;
                $possible += $max;
                $categoryEarned += $points;
                $categoryPossible += $max;
                $critical = $critical || ((bool) $criterion['is_critical'] && $points === 0.0);
                if ($criterion['skill_id'] ?? null) {
                    $key = (string) $criterion['skill_id'];
                    $skills[$key] ??= ['id' => $criterion['skill_id'], 'name' => $criterion['skill_name'], 'earned' => 0.0, 'possible' => 0.0];
                    $skills[$key]['earned'] += $points;
                    $skills[$key]['possible'] += $max;
                }
            }
            $categories[] = ['name' => $category['name'], 'earned' => $categoryEarned, 'possible' => $categoryPossible, 'percentage' => $categoryPossible > 0 ? round($categoryEarned / $categoryPossible * 100, 2) : null];
        }

        if ($forSubmission && $missing) {
            throw ValidationException::withMessages(['submission' => 'Complete required criteria: '.implode(', ', $missing)]);
        }
        if ($forSubmission && $possible <= 0) {
            throw ValidationException::withMessages(['submission' => 'At least one applicable scored criterion is required.']);
        }
        $percentage = $possible > 0 ? round($earned / $possible * 100, 2) : null;
        $threshold = (float) ($evaluation->scorecard_snapshot['passing_score'] ?? 0);
        $result = $percentage === null ? null : (($percentage >= $threshold && ! $critical) ? 'passed' : ($critical ? 'failed_critical' : 'failed'));

        return compact('earned', 'possible', 'percentage', 'critical', 'result', 'missing', 'naCount', 'completed', 'categories', 'skills');
    }
}
