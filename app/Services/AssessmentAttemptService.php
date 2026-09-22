<?php

namespace App\Services;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentBankQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentAttemptService
{
    public function requiredTrainingComplete(AssessmentAssignment $assignment): bool
    {
        $required = $assignment->assessment->trainingMaterials()->where('is_active', true)->where('is_required', true);
        $count = (clone $required)->count();
        if ($count === 0) {
            $directComplete = true;
        } else {
            $directComplete = (clone $required)->whereHas('trainingProgress', fn ($q) => $q->where('employee_id', $assignment->employee_id)->whereNotNull('completed_at'))->count() === $count;
        }
        $attachments = $assignment->assessment->trainingAttachments()->where('is_required', true)
            ->whereHas('material', fn ($q) => $q->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->when($assignment->campaign_id, fn ($x, $campaign) => $x->orWhereHas('campaigns', fn ($c) => $c->whereKey($campaign)))));
        $attachmentComplete = (clone $attachments)->whereHas('progress', fn ($q) => $q->where('employee_id', $assignment->employee_id)->whereNotNull('completed_at'))->count() === $attachments->count();

        return $directComplete && $attachmentComplete;
    }

    public function start(AssessmentAssignment $assignment, User $employee): AssessmentAttempt
    {
        return DB::transaction(function () use ($assignment, $employee): AssessmentAttempt {
            $assignment = AssessmentAssignment::query()->with('assessment')->lockForUpdate()->findOrFail($assignment->id);
            abort_unless($assignment->employee_id === $employee->id, 403);
            $assessment = $assignment->assessment;
            $now = now();

            if ($active = $assignment->attempts()->where('employee_id', $employee->id)->where('status', 'in_progress')->latest('id')->first()) {
                $hasQuestionSource = $assessment->questions()->whereNull('generated_for_attempt_id')->exists()
                    || $assessment->randomPools()->exists();
                if (! empty($active->question_snapshot) || ! $hasQuestionSource || $active->answers()->exists()) {
                    return $active;
                }

                DB::table('assessment_activity_logs')
                    ->where('target_type', AssessmentAttempt::class)
                    ->where('target_id', $active->id)
                    ->delete();
                $active->delete();
            }
            throw_if($assessment->status !== 'published', ValidationException::withMessages(['assessment' => 'This assessment is not published.']));
            $available = $assignment->effectiveAvailableAt();
            $due = $assignment->effectiveDueAt();
            throw_if($available && $now->lt($available), ValidationException::withMessages(['assessment' => 'This assessment is not available yet.']));
            throw_if($due && $now->gt($due), ValidationException::withMessages(['assessment' => 'The due date has passed.']));
            throw_if(! $this->requiredTrainingComplete($assignment), ValidationException::withMessages(['training' => 'Complete all required training before starting.']));
            throw_if(
                ! $assessment->questions()->whereNull('generated_for_attempt_id')->exists() && ! $assessment->randomPools()->exists(),
                ValidationException::withMessages(['assessment' => 'This assessment has no questions. Please contact your administrator.'])
            );

            $used = $assignment->attempts()->where('employee_id', $employee->id)->count();
            throw_if($used > 0 && ! $assessment->allow_retake, ValidationException::withMessages(['attempt' => 'Retakes are not allowed.']));
            throw_if($used >= $assessment->maximum_attempts, ValidationException::withMessages(['attempt' => 'Maximum attempts reached.']));

            $attempt = $assignment->attempts()->create([
                'assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'campaign_id' => $assignment->campaign_id, 'campaign_name' => $assignment->campaign_name, 'attempt_number' => $used + 1,
                'question_snapshot' => [], 'started_at' => $now,
                'expires_at' => $assessment->time_limit_minutes ? $now->copy()->addMinutes($assessment->time_limit_minutes) : null,
                'status' => 'in_progress',
            ]);
            $questions = $assessment->questions()->whereNull('generated_for_attempt_id')->with(['options', 'skill'])->orderBy('display_order')->get()->map(function ($question): array {
                return [
                    'id' => $question->id, 'text' => $question->question_text, 'type' => $question->question_type,
                    'points' => (float) $question->points, 'required' => (bool) $question->is_required,
                    'skill_id' => $question->skill_id, 'skill_name' => $question->skill?->name,
                    'feedback' => $question->feedback, 'source_question_bank_id' => $question->source_bank_question_id,
                    'options' => $question->options->map(fn ($option) => ['id' => $option->id, 'text' => $option->option_text, 'correct' => (bool) $option->is_correct])->all(),
                ];
            })->all();
            $usedBankIds = $assignment->attempts()->whereKeyNot($attempt->id)->get(['question_snapshot'])->flatMap(fn ($prior) => $prior->question_snapshot ?? [])->pluck('source_question_bank_id')->filter()->unique();
            $selected = collect();
            foreach ($assessment->randomPools()->orderBy('display_order')->get() as $pool) {
                $eligible = AssessmentBankQuestion::query()->with(['options', 'skill', 'category'])->where('status', 'active')
                    ->where(fn ($q) => $q->where('applies_to_all_campaigns', true)->when($assignment->campaign_id, fn ($x, $campaign) => $x->orWhereHas('campaigns', fn ($c) => $c->whereKey($campaign))))
                    ->when($pool->skill_id, fn ($q, $v) => $q->where('skill_id', $v))->when($pool->category_id, fn ($q, $v) => $q->where('category_id', $v))
                    ->when($pool->difficulty, fn ($q, $v) => $q->where('difficulty', $v))->when($pool->question_type, fn ($q, $v) => $q->where('question_type', $v))
                    ->whereNotIn('id', $selected->pluck('id'))->get();
                throw_if($eligible->count() < $pool->questions_to_select, ValidationException::withMessages(['pool' => 'Random pools overlap and cannot provide enough distinct active questions.']));
                $chosen = $eligible->sortBy(fn ($q) => [$assessment->reduce_repeated_questions && $usedBankIds->contains($q->id) ? 1 : 0, random_int(0, PHP_INT_MAX)])->take($pool->questions_to_select);
                foreach ($chosen as $bank) {
                    $generated = $assessment->questions()->create(['skill_id' => $bank->skill_id, 'source_bank_question_id' => $bank->id, 'generated_for_attempt_id' => $attempt->id, 'question_text' => $bank->question_text, 'question_type' => $bank->question_type, 'points' => $pool->points_per_question, 'feedback' => $bank->feedback, 'display_order' => count($questions) + 1, 'is_required' => true]);
                    $generated->options()->createMany($bank->options->map->only(['option_text', 'is_correct', 'display_order'])->all());
                    $questions[] = ['id' => $generated->id, 'text' => $bank->question_text, 'type' => $bank->question_type, 'points' => (float) $pool->points_per_question, 'required' => true, 'skill_id' => $bank->skill_id, 'skill_name' => $bank->skill?->name, 'category_id' => $bank->category_id, 'category_name' => $bank->category?->name, 'difficulty' => $bank->difficulty, 'feedback' => $bank->feedback, 'source_question_bank_id' => $bank->id, 'options' => $generated->options()->get()->map(fn ($option) => ['id' => $option->id, 'text' => $option->option_text, 'correct' => (bool) $option->is_correct])->all()];
                }
                $selected = $selected->concat($chosen);
            }
            if ($assessment->randomize_questions) {
                shuffle($questions);
            }
            if ($assessment->randomize_answers) {
                foreach ($questions as &$question) {
                    shuffle($question['options']);
                }
            }

            $attempt->update(['question_snapshot' => $questions]);
            DB::table('assessment_activity_logs')->insert(['actor_id' => $employee->id, 'action' => 'Assessment Attempt Started', 'target_type' => AssessmentAttempt::class, 'target_id' => $attempt->id, 'metadata' => json_encode(['assignment_id' => $assignment->id]), 'created_at' => $now]);

            return $attempt;
        }, 3);
    }

    public function saveAnswer(AssessmentAttempt $attempt, int $questionId, mixed $value): AssessmentAnswer
    {
        $this->assertWritable($attempt);
        $question = collect($attempt->question_snapshot)->firstWhere('id', $questionId);
        throw_if(! $question, ValidationException::withMessages(['question_id' => 'Question does not belong to this attempt.']));
        $answer = $this->normalizeAnswer($question, $value);

        return AssessmentAnswer::query()->updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $questionId],
            ['question_snapshot' => $question, 'answer' => $answer, 'points_awarded' => null, 'is_correct' => null, 'requires_manual_review' => $question['type'] === 'short_answer']
        );
    }

    public function submit(AssessmentAttempt $attempt): AssessmentAttempt
    {
        return DB::transaction(function () use ($attempt): AssessmentAttempt {
            $attempt = AssessmentAttempt::query()->with('assessment')->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== 'in_progress') {
                return $attempt;
            }

            $answers = $attempt->answers()->get()->keyBy('question_id');
            $earned = 0.0;
            $possible = 0.0;
            $manual = false;
            $skills = [];
            foreach ($attempt->question_snapshot as $question) {
                $points = (float) $question['points'];
                $possible += $points;
                $answer = $answers->get($question['id']);
                $awarded = 0.0;
                $correct = false;
                if ($question['type'] === 'short_answer') {
                    $manual = true;
                    if ($answer) {
                        $answer->update(['requires_manual_review' => true, 'points_awarded' => null, 'is_correct' => null]);
                    }
                } else {
                    $selected = collect($answer?->answer['option_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
                    $correctIds = collect($question['options'])->where('correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                    $correct = $selected === $correctIds && count($selected) > 0;
                    $awarded = $correct ? $points : 0.0;
                    $earned += $awarded;
                    if ($answer) {
                        $answer->update(['points_awarded' => $awarded, 'is_correct' => $correct, 'requires_manual_review' => false]);
                    }
                }
                if ($question['skill_id']) {
                    $key = (int) $question['skill_id'];
                    $skills[$key] ??= ['earned' => 0.0, 'possible' => 0.0];
                    $skills[$key]['earned'] += $awarded;
                    $skills[$key]['possible'] += $points;
                }
            }
            $percentage = $possible > 0 ? round($earned / $possible * 100, 2) : 0;
            foreach ($skills as $skillId => $score) {
                $attempt->skillResults()->updateOrCreate(['skill_id' => $skillId], ['points_earned' => $score['earned'], 'points_possible' => $score['possible'], 'percentage' => $score['possible'] > 0 ? round($score['earned'] / $score['possible'] * 100, 2) : 0]);
            }
            $submittedAt = now();
            $elapsedSeconds = max(0, $attempt->started_at->diffInSeconds($submittedAt, false));
            $allowedSeconds = $attempt->expires_at
                ? max(0, $attempt->started_at->diffInSeconds($attempt->expires_at, false))
                : PHP_INT_MAX;
            $attempt->update(['submitted_at' => $submittedAt, 'status' => $manual ? 'pending_review' : ($percentage >= (float) $attempt->assessment->passing_score ? 'passed' : 'failed'), 'points_earned' => $earned, 'total_points' => $possible, 'percentage' => $percentage, 'passed' => $manual ? null : $percentage >= (float) $attempt->assessment->passing_score, 'time_taken_seconds' => min($elapsedSeconds, $allowedSeconds)]);
            $attempt->assignment()->update(['status' => $manual ? 'pending_review' : ($attempt->passed ? 'passed' : 'failed')]);
            DB::table('assessment_activity_logs')->insert(['actor_id' => $attempt->employee_id, 'action' => 'Assessment Submitted', 'target_type' => AssessmentAttempt::class, 'target_id' => $attempt->id, 'metadata' => json_encode(['percentage' => $percentage]), 'created_at' => $submittedAt]);

            return $attempt->fresh();
        }, 3);
    }

    public function assertWritable(AssessmentAttempt $attempt): void
    {
        throw_if($attempt->status !== 'in_progress', ValidationException::withMessages(['attempt' => 'This attempt has already been submitted.']));
        if ($attempt->expires_at && now()->gte($attempt->expires_at)) {
            $this->submit($attempt);
            throw ValidationException::withMessages(['attempt' => 'Time has expired and the attempt was submitted.']);
        }
    }

    private function normalizeAnswer(array $question, mixed $value): array
    {
        if ($question['type'] === 'short_answer') {
            return ['text' => trim((string) ($value['text'] ?? ''))];
        }
        $ids = collect($value['option_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $allowed = collect($question['options'])->pluck('id')->map(fn ($id) => (int) $id);
        throw_if($ids->diff($allowed)->isNotEmpty(), ValidationException::withMessages(['answer' => 'An invalid option was selected.']));
        throw_if(in_array($question['type'], ['multiple_choice', 'true_false'], true) && $ids->count() > 1, ValidationException::withMessages(['answer' => 'Select one answer only.']));

        return ['option_ids' => $ids->all()];
    }
}
