<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentGradingService
{
    public function __construct(private AssessmentNotificationService $notifications) {}

    public function save(AssessmentAttempt $attempt, User $grader, array $grades, bool $finalize): AssessmentAttempt
    {
        return DB::transaction(function () use ($attempt, $grader, $grades, $finalize): AssessmentAttempt {
            $attempt = AssessmentAttempt::query()->with(['assessment', 'answers'])->lockForUpdate()->findOrFail($attempt->id);
            throw_if($attempt->status !== 'pending_review', ValidationException::withMessages(['attempt' => 'Only pending attempts can be graded.']));
            throw_if(empty($attempt->question_snapshot), ValidationException::withMessages(['attempt' => 'This legacy attempt has no grading snapshot and cannot be safely finalized.']));
            $manual = $attempt->answers->where('requires_manual_review', true)->keyBy('id');
            foreach ($grades as $grade) {
                $answer = $manual->get((int) $grade['answer_id']);
                throw_if(! $answer, ValidationException::withMessages(['grades' => 'The response does not belong to this pending attempt.']));
                $maximum = (float) $answer->question_snapshot['points'];
                throw_if($grade['points_awarded'] < 0 || $grade['points_awarded'] > $maximum, ValidationException::withMessages(['grades' => "Points must be between 0 and {$maximum}."]));
                $answer->update(['points_awarded' => $grade['points_awarded'], 'grader_feedback' => $grade['grader_feedback'] ?? null, 'graded_by' => $grader->id, 'graded_at' => now()]);
            }
            if (! $finalize) {
                return $attempt->fresh();
            }
            $attempt->load('answers');
            throw_if($attempt->answers->where('requires_manual_review', true)->contains(fn ($answer) => $answer->graded_at === null), ValidationException::withMessages(['grades' => 'Grade every manual response before finalizing.']));

            $earned = (float) $attempt->answers->sum(fn ($answer) => (float) ($answer->points_awarded ?? 0));
            $possible = collect($attempt->question_snapshot ?? [])->sum('points');
            $percentage = $possible > 0 ? round($earned / $possible * 100, 2) : 0;
            $passed = $percentage >= (float) $attempt->assessment->passing_score;
            $skills = [];
            foreach ($attempt->question_snapshot ?? [] as $question) {
                if (! $question['skill_id']) {
                    continue;
                }
                $answer = $attempt->answers->firstWhere('question_id', $question['id']);
                $skillId = (int) $question['skill_id'];
                $skills[$skillId] ??= ['earned' => 0.0, 'possible' => 0.0];
                $skills[$skillId]['earned'] += (float) ($answer?->points_awarded ?? 0);
                $skills[$skillId]['possible'] += (float) $question['points'];
            }
            foreach ($skills as $skillId => $score) {
                $attempt->skillResults()->updateOrCreate(['skill_id' => $skillId], ['points_earned' => $score['earned'], 'points_possible' => $score['possible'], 'percentage' => $score['possible'] ? round($score['earned'] / $score['possible'] * 100, 2) : 0]);
            }
            $attempt->update(['points_earned' => $earned, 'total_points' => $possible, 'percentage' => $percentage, 'passed' => $passed, 'status' => $passed ? 'passed' : 'failed']);
            $attempt->assignment()->update(['status' => $passed ? 'passed' : 'failed']);
            DB::table('assessment_activity_logs')->insert(['actor_id' => $grader->id, 'action' => 'Assessment Manual Grading Completed', 'target_type' => AssessmentAttempt::class, 'target_id' => $attempt->id, 'metadata' => json_encode(['assessment_id' => $attempt->assessment_id, 'employee_id' => $attempt->employee_id, 'percentage' => $percentage, 'status' => $attempt->status]), 'created_at' => now()]);
            $this->notifications->create($attempt->assignment, 'grading_complete', 'Assessment Grading Complete', "Your {$attempt->assessment->title} assessment has been reviewed. Final score: {$percentage}%.", route('assessments.my.results', $attempt));

            return $attempt->fresh();
        }, 3);
    }
}
