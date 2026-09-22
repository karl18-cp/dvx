<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_owned_paginated_and_preserves_every_attempt(): void
    {
        [$employee, $admin, $assessment, $assignment] = $this->context();
        foreach (range(1, 16) as $number) {
            $this->attempt($assignment, $number, 'failed', $number);
        }
        [$other,,, $otherAssignment] = $this->context();
        $this->attempt($otherAssignment, 1, 'passed', 100);
        $this->actingAs($employee)->get(route('assessments.history'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('attempts.data', 15)->where('attempts.total', 16));
        $this->actingAs($other)->get(route('assessments.history'))->assertInertia(fn (Assert $page) => $page->has('attempts.data', 1));
    }

    public function test_progress_uses_best_finalized_attempt_and_excludes_pending_review(): void
    {
        [$employee,,,$assignment] = $this->context();
        $this->attempt($assignment, 1, 'failed', 60);
        $this->attempt($assignment, 2, 'passed', 90);
        $this->attempt($assignment, 3, 'pending_review', 100);
        $this->actingAs($employee)->get(route('assessments.progress'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.completed', 1)->where('summary.average_score', 90)->where('summary.pass_rate', 100)->where('summary.pending_review', 1));
    }

    public function test_only_management_can_view_queue_and_grade_points_are_bounded(): void
    {
        [$employee, $admin,, $assignment] = $this->context();
        $snapshot = [['id' => 10, 'text' => 'Explain', 'type' => 'short_answer', 'points' => 5, 'skill_id' => null, 'skill_name' => null, 'options' => []]];
        $attempt = $this->attempt($assignment, 1, 'pending_review', 0, $snapshot);
        $answer = $this->manualAnswer($attempt, 5);
        $this->actingAs($employee)->get(route('assessments.reports.pending'))->assertForbidden();
        $this->actingAs($admin)->get(route('assessments.reports.pending'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('attempts.data', 1));
        $this->actingAs($admin)->put(route('assessments.reports.grade.save', $attempt), ['grades' => [['answer_id' => $answer->id, 'points_awarded' => 6]], 'finalize' => true])->assertSessionHasErrors('grades');
        $this->actingAs($admin)->put(route('assessments.reports.grade.save', $attempt), ['grades' => [['answer_id' => $answer->id, 'points_awarded' => -1]], 'finalize' => true])->assertSessionHasErrors();
    }

    public function test_partial_save_stays_pending_and_finalize_recalculates_score_skill_and_feedback(): void
    {
        [$employee, $admin,, $assignment] = $this->context(['passing_score' => 70]);
        $skill = AssessmentSkill::query()->create(['name' => 'Writing']);
        $attempt = $this->attempt($assignment, 1, 'pending_review', 0, [['id' => 10, 'text' => 'One', 'type' => 'short_answer', 'points' => 5, 'skill_id' => $skill->id, 'skill_name' => 'Writing', 'options' => []], ['id' => 11, 'text' => 'Two', 'type' => 'short_answer', 'points' => 5, 'skill_id' => $skill->id, 'skill_name' => 'Writing', 'options' => []]]);
        $one = $this->manualAnswer($attempt, 5, 10);
        $two = $this->manualAnswer($attempt, 5, 11);
        $this->actingAs($admin)->put(route('assessments.reports.grade.save', $attempt), ['grades' => [['answer_id' => $one->id, 'points_awarded' => 4, 'grader_feedback' => 'Good']], 'finalize' => false])->assertRedirect();
        $this->assertSame('pending_review', $attempt->fresh()->status);
        $this->actingAs($admin)->from(route('assessments.reports.grade', $attempt))->put(route('assessments.reports.grade.save', $attempt), ['grades' => [['answer_id' => $two->id, 'points_awarded' => 3, 'grader_feedback' => 'Improve']], 'finalize' => true])->assertRedirect(route('assessments.reports.pending'))->assertSessionHas('status', 'Grading finalized.');
        $this->get(route('assessments.reports.pending'))->assertOk();
        $attempt->refresh();
        $this->assertSame('passed', $attempt->status);
        $this->assertEquals(70, $attempt->percentage);
        $this->assertDatabaseCount('assessment_skill_results', 1);
        $this->assertDatabaseHas('assessment_answers', ['id' => $one->id, 'grader_feedback' => 'Good']);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Assessment Manual Grading Completed', 'target_id' => $attempt->id]);
        $this->actingAs($employee)->get(route('assessments.my.results', $attempt))->assertInertia(fn (Assert $page) => $page->where('result.questions.0.grader_feedback', 'Good'));
    }

    public function test_reopening_finalized_grading_redirects_safely_without_changing_scores(): void
    {
        [$employee, $admin, , $assignment] = $this->context();
        foreach (['passed' => 80, 'failed' => 40] as $status => $score) {
            $attempt = $this->attempt($assignment, $status === 'passed' ? 1 : 2, $status, $score);
            $this->actingAs($admin)->get(route('assessments.reports.grade', $attempt))
                ->assertRedirect(route('assessments.reports.pending'))
                ->assertSessionHas('status', 'This grading has already been finalized. You can view the score in Results & Analytics.');
            $this->get(route('assessments.reports.pending'))->assertOk();
            $this->assertSame($status, $attempt->fresh()->status);
            $this->assertEquals($score, $attempt->fresh()->percentage);
            $this->actingAs($employee)->get(route('assessments.reports.grade', $attempt))->assertForbidden();
        }
    }

    public function test_pending_grading_still_opens_and_finalized_scores_cannot_be_overwritten(): void
    {
        [, $admin, , $assignment] = $this->context();
        $snapshot = [['id' => 10, 'text' => 'Explain', 'type' => 'short_answer', 'points' => 5, 'skill_id' => null, 'skill_name' => null, 'options' => []]];
        $attempt = $this->attempt($assignment, 1, 'pending_review', 0, $snapshot);
        $answer = $this->manualAnswer($attempt, 5);
        $this->actingAs($admin)->get(route('assessments.reports.grade', $attempt))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('assessments/grade')->where('attempt.id', $attempt->id));
        $this->put(route('assessments.reports.grade.save', $attempt), ['grades' => [['answer_id' => $answer->id, 'points_awarded' => 4]], 'finalize' => true])
            ->assertRedirect(route('assessments.reports.pending'));
        $this->from(route('assessments.reports.grade', $attempt))->put(route('assessments.reports.grade.save', $attempt), ['grades' => [['answer_id' => $answer->id, 'points_awarded' => 0]], 'finalize' => true])->assertSessionHasErrors('attempt');
        $this->get(route('assessments.reports.grade', $attempt))->assertRedirect(route('assessments.reports.pending'));
        $this->assertEquals(4, $answer->fresh()->points_awarded);
        $this->assertEquals(80, $attempt->fresh()->percentage);
    }

    public function test_analytics_uses_best_finalized_attempt_and_snapshot_question_counts(): void
    {
        [, $admin, $assessment, $assignment] = $this->context();
        $snapshot = [['id' => 77, 'text' => 'Historical wording', 'type' => 'multiple_choice', 'points' => 1, 'skill_id' => null, 'skill_name' => null, 'options' => [['id' => 1, 'text' => 'A', 'correct' => true], ['id' => 2, 'text' => 'B', 'correct' => false]]]];
        $this->attempt($assignment, 1, 'failed', 40, $snapshot);
        $best = $this->attempt($assignment, 2, 'passed', 90, $snapshot);
        $this->attempt($assignment, 3, 'pending_review', 100, $snapshot);
        DB::table('assessment_questions')->insert(['id' => 77, 'assessment_id' => $assessment->id, 'question_text' => 'Current wording', 'question_type' => 'multiple_choice', 'points' => 1, 'display_order' => 1, 'is_required' => true, 'created_at' => now(), 'updated_at' => now()]);
        AssessmentAnswer::query()->create(['attempt_id' => $best->id, 'question_id' => 77, 'question_snapshot' => $snapshot[0], 'answer' => ['option_ids' => [1]], 'points_awarded' => 1, 'is_correct' => true]);
        $this->actingAs($admin)->get(route('assessments.reports.analytics', $assessment))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.completed', 1)->where('summary.average_score', 90)->where('questions.0.question', 'Historical wording')->where('questions.0.correct', 1));
    }

    public function test_equal_best_scores_count_as_one_finalized_employee_outcome(): void
    {
        [, $admin, $assessment, $assignment] = $this->context();
        $this->attempt($assignment, 1, 'passed', 90);
        $this->attempt($assignment, 2, 'passed', 90);

        $this->actingAs($admin)->get(route('assessments.reports.analytics', $assessment))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.completed', 1)->where('summary.passed', 1)->where('summary.average_score', 90));
    }

    public function test_legacy_attempt_without_snapshot_does_not_break_assessment_analytics(): void
    {
        [, $admin, $assessment, $assignment] = $this->context();
        $this->attempt($assignment, 1, 'passed', 80, []);

        $this->actingAs($admin)->get(route('assessments.reports.analytics', $assessment))->assertOk()->assertInertia(fn (Assert $page) => $page->has('questions', 0));
    }

    public function test_filtered_csv_export_is_authorized_filtered_and_formula_safe(): void
    {
        [$employee, $admin, $assessment, $assignment] = $this->context();
        $employee->update(['name' => '=SUM(1,1)', 'username' => '+agent']);
        $attempt = $this->attempt($assignment, 1, 'failed', 40);
        [, , , $otherAssignment] = $this->context();
        $this->attempt($otherAssignment, 1, 'passed', 100);

        $this->actingAs($employee)->get(route('assessments.reports.export'))->assertForbidden();
        $response = $this->actingAs($admin)->get(route('assessments.reports.export', ['assessment' => $assessment->id, 'status' => 'failed']));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'+agent", $csv);
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringContainsString($assessment->title, $csv);
        $this->assertStringNotContainsString('100,75,passed', $csv);
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attempt->id]);
    }

    private function context(array $settings = []): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $assessment = Assessment::query()->create(array_merge(['title' => 'Report', 'passing_score' => 75, 'maximum_attempts' => 20, 'status' => 'published', 'created_by' => $admin->id], $settings));
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);

        return [$employee, $admin, $assessment, $assignment];
    }

    private function attempt(AssessmentAssignment $assignment, int $number, string $status, float $percentage, array $snapshot = []): AssessmentAttempt
    {
        return AssessmentAttempt::query()->create(['assessment_id' => $assignment->assessment_id, 'employee_id' => $assignment->employee_id, 'assignment_id' => $assignment->id, 'attempt_number' => $number, 'question_snapshot' => $snapshot, 'started_at' => now()->subMinutes(10), 'submitted_at' => now(), 'status' => $status, 'points_earned' => $percentage, 'total_points' => 100, 'percentage' => $percentage, 'passed' => $status === 'passed', 'time_taken_seconds' => 600]);
    }

    private function manualAnswer(AssessmentAttempt $attempt, float $points, int $questionId = 10): AssessmentAnswer
    {
        DB::table('assessment_questions')->insertOrIgnore(['id' => $questionId, 'assessment_id' => $attempt->assessment_id, 'skill_id' => $attempt->question_snapshot[0]['skill_id'] ?? null, 'question_text' => 'Explain', 'question_type' => 'short_answer', 'points' => $points, 'display_order' => $questionId, 'is_required' => true, 'created_at' => now(), 'updated_at' => now()]);

        return AssessmentAnswer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $questionId, 'question_snapshot' => ['id' => $questionId, 'text' => 'Explain', 'type' => 'short_answer', 'points' => $points, 'skill_id' => $attempt->question_snapshot[0]['skill_id'] ?? null, 'skill_name' => $attempt->question_snapshot[0]['skill_name'] ?? null, 'options' => []], 'answer' => ['text' => 'Response'], 'requires_manual_review' => true]);
    }
}
