<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;

class AssessmentReadinessService
{
    /** @return list<string> */
    public function errors(Assessment $assessment): array
    {
        $assessment->loadMissing(['campaigns:id', 'trainingMaterials', 'trainingAttachments.material', 'questions.options', 'randomPools']);
        $errors = [];

        if (trim($assessment->title) === '') {
            $errors[] = 'Assessment title is required.';
        }
        if (! $assessment->applies_to_all_campaigns && $assessment->campaigns->isEmpty()) {
            $errors[] = 'Select at least one campaign or choose All Campaigns.';
        }
        foreach ($assessment->trainingMaterials->where('is_active', true)->where('is_required', true) as $material) {
            if ($material->type === 'written' ? trim((string) $material->content) === '' : ! $material->storage_key) {
                $errors[] = "Required training '{$material->title}' is incomplete.";
            }
        }
        foreach ($assessment->trainingAttachments->where('is_required', true) as $attachment) {
            if (! $attachment->material || $attachment->material->status !== 'active') {
                $errors[] = 'A required Training Library item is unavailable.';
            }
        }

        $questions = $assessment->questions->whereNull('generated_for_attempt_id')->values();
        if ($assessment->simple_builder_enabled) {
            if ($questions->count() !== $assessment->planned_question_count) {
                $errors[] = "Expected {$assessment->planned_question_count} question slots, but found {$questions->count()}.";
            }
            if (round((float) $questions->sum('points'), 2) !== round((float) $assessment->planned_total_points, 2)) {
                $errors[] = "Question points must total {$assessment->planned_total_points}.";
            }
        } elseif ($questions->isEmpty() && $assessment->randomPools->isEmpty()) {
            $errors[] = 'Add at least one question or random question pool.';
        }

        foreach ($questions as $index => $question) {
            foreach ($this->questionErrors($question, $index + 1) as $error) {
                $errors[] = $error;
            }
        }
        if ((float) $assessment->passing_score < 0 || (float) $assessment->passing_score > 100) {
            $errors[] = 'Passing score must be between 0 and 100%.';
        }
        if ($assessment->time_limit_minutes !== null && $assessment->time_limit_minutes < 1) {
            $errors[] = 'Time limit must be at least one minute or disabled.';
        }
        if ($assessment->maximum_attempts < 1) {
            $errors[] = 'Maximum attempts must be at least one.';
        }

        return array_values(array_unique($errors));
    }

    /** @return list<string> */
    public function questionErrors(AssessmentQuestion $question, int $number): array
    {
        if (trim($question->question_text) === '') {
            return ["Question {$number} is incomplete."];
        }
        if ($question->question_type === 'short_answer') {
            return [];
        }
        $options = $question->options;
        $correct = $options->where('is_correct', true)->count();
        if ($question->question_type === 'true_false') {
            $labels = $options->pluck('option_text')->map(fn ($value) => strtolower(trim($value)))->sort()->values()->all();
            if ($labels !== ['false', 'true'] || $correct !== 1) {
                return ["Question {$number} needs one valid True/False answer."];
            }
        } elseif ($options->count() < 2) {
            return ["Question {$number} needs at least two answer choices."];
        } elseif ($question->question_type === 'multiple_choice' && $correct !== 1) {
            return ["Question {$number} must have exactly one correct answer."];
        } elseif ($question->question_type === 'multiple_selection' && $correct < 1) {
            return ["Question {$number} needs at least one correct answer."];
        }

        return [];
    }
}
