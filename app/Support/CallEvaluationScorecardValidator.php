<?php

namespace App\Support;

use App\Models\CallEvaluationScorecard;

class CallEvaluationScorecardValidator
{
    /** @return list<string> */
    public function activationErrors(CallEvaluationScorecard $scorecard): array
    {
        $scorecard->loadMissing(['campaigns:id,is_active', 'categories.criteria.skill:id,is_active']);
        $errors = [];

        if (blank(trim($scorecard->name))) {
            $errors[] = 'Add a Scorecard name.';
        }
        if ((float) $scorecard->passing_score < 0 || (float) $scorecard->passing_score > 100) {
            $errors[] = 'Passing Score must be between 0 and 100.';
        }
        if (! $scorecard->applies_to_all_campaigns && $scorecard->campaigns->isEmpty()) {
            $errors[] = 'Select at least one Campaign or choose All Campaigns.';
        }
        if ($scorecard->campaigns->contains(fn ($campaign) => ! $campaign->is_active)) {
            $errors[] = 'Campaign Scope contains an inactive Campaign.';
        }
        if ($scorecard->categories->isEmpty()) {
            $errors[] = 'Add at least one Category.';
        }

        $criteria = $scorecard->categories->flatMap->criteria;
        if ($criteria->isEmpty()) {
            $errors[] = 'Add at least one Criterion.';
        }
        foreach ($criteria as $criterion) {
            if ((float) $criterion->points_possible <= 0) {
                $errors[] = "\"{$criterion->label}\" must have Points greater than 0.";
            }
            if ($criterion->skill_id && (! $criterion->skill || ! $criterion->skill->is_active)) {
                $errors[] = "\"{$criterion->label}\" uses an inactive or missing Skill.";
            }
        }
        if ($criteria->sum(fn ($criterion) => (float) $criterion->points_possible) <= 0) {
            $errors[] = 'Total Possible Points must be greater than 0.';
        }
        $categoryOrder = $scorecard->categories->pluck('display_order')->map(fn ($order) => (int) $order)->all();
        if ($categoryOrder !== [] && $categoryOrder !== range(1, count($categoryOrder))) {
            $errors[] = 'Category ordering is invalid. Reorder a Category before activation.';
        }
        foreach ($scorecard->categories as $category) {
            $criterionOrder = $category->criteria->pluck('display_order')->map(fn ($order) => (int) $order)->all();
            if ($criterionOrder !== [] && $criterionOrder !== range(1, count($criterionOrder))) {
                $errors[] = "Criterion ordering in \"{$category->name}\" is invalid.";
            }
        }

        return array_values(array_unique($errors));
    }
}
