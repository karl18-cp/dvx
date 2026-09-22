<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use App\Models\AssessmentTrainingMaterial;
use App\Models\AssessmentTrainingProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentEmployeeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_assessment_fails_safely_without_creating_or_rendering_a_blank_attempt(): void
    {
        $employee = User::factory()->create(['role' => 'agent']);
        $admin = User::factory()->create(['role' => 'admin']);
        $assessment = Assessment::query()->create(['title' => 'Empty', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);

        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertSessionHasErrors('assessment');
        $this->assertDatabaseCount('assessment_attempts', 0);

        $legacyAttempt = AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'assignment_id' => $assignment->id, 'employee_id' => $employee->id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now(), 'status' => 'in_progress']);
        $this->actingAs($employee)->get(route('assessments.my.attempts.show', $legacyAttempt))->assertRedirect(route('assessments.index'))->assertSessionHasErrors('assessment');
        $this->actingAs($employee)->get(route('assessments.index'))->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.status', 'configuration_error')
            ->where('assignments.0.configuration_error', 'This assessment has no questions. Please contact your administrator.')
            ->where('assignments.0.active_attempt_id', null));

        $this->question($assessment, 'Added after the invalid attempt');
        $this->actingAs($employee)->get(route('assessments.my.attempts.show', $legacyAttempt))->assertRedirect();
        $replacement = AssessmentAttempt::query()->sole();
        $this->assertNotSame($legacyAttempt->id, $replacement->id);
        $this->assertCount(1, $replacement->question_snapshot);
        $this->actingAs($employee)->get(route('assessments.my.attempts.show', $replacement))->assertOk();
    }

    public function test_employee_sees_only_own_assignment_and_cannot_open_another_assignment(): void
    {
        [$employee, $assignment] = $this->context();
        [$other, $otherAssignment] = $this->context();

        $this->actingAs($employee)->get(route('assessments.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('assignments', 1)->where('assignments.0.id', $assignment->id));
        $this->actingAs($employee)->get(route('assessments.my.training', $otherAssignment))->assertForbidden();
        $this->actingAs($other)->get(route('assessments.my.training', $assignment))->assertForbidden();
    }

    public function test_multiple_same_week_assessments_are_independent_ordered_and_counted_in_manila_time(): void
    {
        Carbon::setTestNow('2026-08-16 16:30:00'); // Monday 00:30 in Manila.
        [$employee, $completed] = $this->context();
        $completed->update(['available_from' => Carbon::parse('2026-08-17 01:00:00'), 'due_date' => Carbon::parse('2026-08-18 09:00:00'), 'status' => 'passed']);
        AssessmentAttempt::query()->create(['assessment_id' => $completed->assessment_id, 'employee_id' => $employee->id, 'assignment_id' => $completed->id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now()->subHour(), 'submitted_at' => now(), 'status' => 'passed', 'percentage' => 100, 'passed' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $dueTodayAssessment = Assessment::query()->create(['title' => 'Call Handling', 'passing_score' => 80, 'maximum_attempts' => 2, 'status' => 'published', 'created_by' => $admin->id]);
        $dueToday = AssessmentAssignment::query()->create(['assessment_id' => $dueTodayAssessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'available_from' => now()->subMinute(), 'due_date' => Carbon::parse('2026-08-17 09:00:00'), 'status' => 'assigned']);

        $this->actingAs($employee)->get(route('assessments.index'))->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 2)
            ->where('assignments.0.id', $dueToday->id)
            ->where('assignments.0.maximum_attempts', 2)
            ->where('assignments.1.id', $completed->id)
            ->where('week_summary.total', 2)
            ->where('week_summary.completed', 1)
            ->where('week_summary.remaining', 1));
        Carbon::setTestNow();
    }

    public function test_training_gate_requires_opening_and_completion_before_start(): void
    {
        [$employee, $assignment] = $this->context();
        $material = AssessmentTrainingMaterial::query()->create(['assessment_id' => $assignment->assessment_id, 'title' => 'Policy', 'type' => 'written', 'content' => 'Read me', 'is_required' => true, 'required_completion_percentage' => 90, 'display_order' => 1, 'is_active' => true, 'created_by' => $assignment->assigned_by]);

        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertSessionHasErrors('training');
        $this->actingAs($employee)->post(route('assessments.my.materials.complete', [$assignment, $material]))->assertStatus(422);
        $this->actingAs($employee)->post(route('assessments.my.materials.open', [$assignment, $material]))->assertOk();
        $this->actingAs($employee)->post(route('assessments.my.materials.complete', [$assignment, $material]))->assertOk();
        $this->assertNotNull(AssessmentTrainingProgress::query()->first()->completed_at);
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertRedirect();
    }

    public function test_attempt_creation_is_duplicate_safe_and_snapshot_order_is_stable(): void
    {
        [$employee, $assignment] = $this->context(['randomize_questions' => true, 'randomize_answers' => true]);
        $this->question($assignment->assessment, 'One');
        $this->question($assignment->assessment, 'Two');

        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertRedirect();
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $snapshot = $attempt->question_snapshot;
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertRedirect(route('assessments.my.attempts.show', $attempt));
        $this->assertDatabaseCount('assessment_attempts', 1);
        $this->actingAs($employee)->get(route('assessments.my.attempts.show', $attempt))->assertOk();
        $this->assertSame($snapshot, $attempt->fresh()->question_snapshot);
    }

    public function test_autosave_validates_ownership_question_and_option_membership(): void
    {
        [$employee, $assignment] = $this->context();
        $question = $this->question($assignment->assessment, 'Pick');
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $option = $question->options()->firstOrFail();
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), ['question_id' => $question->id, 'answer' => ['option_ids' => [$option->id]]])->assertOk();
        $this->assertDatabaseCount('assessment_answers', 1);
        $this->actingAs(User::factory()->create())->putJson(route('assessments.my.attempts.autosave', $attempt), ['question_id' => $question->id, 'answer' => ['option_ids' => [$option->id]]])->assertForbidden();
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), ['question_id' => 999999, 'answer' => ['option_ids' => []]])->assertStatus(422);
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), ['question_id' => $question->id, 'answer' => ['option_ids' => [999999]]])->assertStatus(422);
    }

    public function test_final_review_is_owned_refreshable_and_preserves_snapshot_order_and_attempt(): void
    {
        [$employee, $assignment] = $this->context(['randomize_questions' => true]);
        $this->question($assignment->assessment, 'First review question');
        $this->question($assignment->assessment, 'Second review question');
        $this->question($assignment->assessment, 'Third review question');
        $assignment->update(['campaign_name' => 'Inbound Pro']);

        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $snapshot = $attempt->question_snapshot;
        $first = $snapshot[0];
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), [
            'question_id' => $first['id'],
            'answer' => ['option_ids' => [$first['options'][0]['id']]],
        ])->assertOk();

        $this->actingAs($employee)->get(route('assessments.my.attempts.review', $attempt))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('assessments/review')
            ->where('attempt.id', $attempt->id)
            ->where('attempt.answered_count', 1)
            ->where('attempt.unanswered_count', 2)
            ->where('attempt.questions.0.id', $snapshot[0]['id'])
            ->where('attempt.questions.1.id', $snapshot[1]['id'])
            ->where('attempt.questions.2.id', $snapshot[2]['id'])
            ->where('attempt.questions.0.answered', true)
            ->where('attempt.questions.1.answered', false));
        $this->actingAs($employee)->get(route('assessments.my.attempts.show', $attempt).'?question=2')->assertOk();
        $this->actingAs($employee)->get(route('assessments.my.attempts.review', $attempt))->assertOk();
        $this->assertDatabaseCount('assessment_attempts', 1);
        $this->assertSame($snapshot, $attempt->fresh()->question_snapshot);
        $this->assertSame('Inbound Pro', $attempt->campaign_name);

        $other = User::factory()->create(['role' => 'agent']);
        $this->actingAs($other)->get(route('assessments.my.attempts.review', $attempt))->assertForbidden();

        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt))->assertRedirect(route('assessments.my.results', $attempt));
        $this->actingAs($employee)->get(route('assessments.my.attempts.review', $attempt))->assertRedirect(route('assessments.my.results', $attempt));
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), [
            'question_id' => $snapshot[1]['id'],
            'answer' => ['option_ids' => [$snapshot[1]['options'][0]['id']]],
        ])->assertStatus(422);
        $this->assertDatabaseCount('assessment_attempts', 1);
        $this->assertSame('Inbound Pro', $attempt->fresh()->campaign_name);
    }

    public function test_expired_review_uses_existing_submission_workflow(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        [$employee, $assignment] = $this->context(['time_limit_minutes' => 1]);
        $this->question($assignment->assessment, 'Timed review question');
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();

        Carbon::setTestNow('2026-08-13 10:02:00');
        $this->actingAs($employee)->get(route('assessments.my.attempts.review', $attempt))->assertRedirect(route('assessments.my.results', $attempt));
        $this->assertNotSame('in_progress', $attempt->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_submission_scores_all_objective_types_and_persists_skills_idempotently(): void
    {
        [$employee, $assignment] = $this->context(['passing_score' => 75]);
        $skill = AssessmentSkill::query()->create(['name' => 'Grammar']);
        $mc = $this->question($assignment->assessment, 'MC', 'multiple_choice', $skill->id);
        $tf = $this->question($assignment->assessment, 'TF', 'true_false', $skill->id);
        $ms = $this->question($assignment->assessment, 'MS', 'multiple_selection', $skill->id, 2);
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        foreach ([$mc, $tf] as $question) {
            $this->save($employee, $attempt, $question, [$question->options()->where('is_correct', true)->value('id')]);
        }
        $this->save($employee, $attempt, $ms, $ms->options()->where('is_correct', true)->pluck('id')->all());
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt))->assertRedirect();
        $attempt->refresh();
        $this->assertSame('passed', $attempt->status);
        $this->assertEquals(100, $attempt->percentage);
        $this->assertDatabaseCount('assessment_skill_results', 1);
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt))->assertRedirect();
        $this->assertDatabaseCount('assessment_skill_results', 1);
        $this->assertDatabaseCount('assessment_activity_logs', 2);
    }

    public function test_wrong_exact_set_fails_and_short_answer_requires_manual_review(): void
    {
        [$employee, $assignment] = $this->context(['passing_score' => 50]);
        $ms = $this->question($assignment->assessment, 'MS', 'multiple_selection', null, 2);
        $short = AssessmentQuestion::query()->create(['assessment_id' => $assignment->assessment_id, 'question_text' => 'Explain', 'question_type' => 'short_answer', 'points' => 2, 'display_order' => 2, 'is_required' => true]);
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $this->save($employee, $attempt, $ms, [$ms->options()->where('is_correct', true)->value('id')]);
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), ['question_id' => $short->id, 'answer' => ['text' => 'My answer']])->assertOk();
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt));
        $this->assertSame('pending_review', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->passed);
    }

    public function test_timer_is_persisted_and_expiry_rejects_late_changes_and_submits(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        [$employee, $assignment] = $this->context(['time_limit_minutes' => 15]);
        $question = $this->question($assignment->assessment, 'Timed');
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $this->assertTrue($attempt->expires_at->equalTo(Carbon::parse('2026-08-13 10:15:00')));
        Carbon::setTestNow('2026-08-13 10:16:00');
        $this->save($employee, $attempt, $question, [$question->options()->first()->id], 422);
        $this->assertNotSame('in_progress', $attempt->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_submission_never_persists_negative_time_for_a_legacy_future_start_timestamp(): void
    {
        [$employee, $assignment] = $this->context();
        $question = $this->question($assignment->assessment, 'Legacy timestamp');
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $attempt->update(['started_at' => now()->addHours(8)]);
        $this->save($employee, $attempt, $question, [$question->options()->where('is_correct', true)->value('id')]);

        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt))->assertRedirect();

        $this->assertSame(0, $attempt->fresh()->time_taken_seconds);
        $this->assertSame('passed', $attempt->fresh()->status);
    }

    public function test_answer_key_is_never_sent_when_disabled_and_result_ownership_is_enforced(): void
    {
        [$employee, $assignment] = $this->context(['show_correct_answers' => false]);
        $question = $this->question($assignment->assessment, 'Secret');
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $this->save($employee, $attempt, $question, [$question->options()->first()->id]);
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt));
        $this->actingAs($employee)->get(route('assessments.my.results', $attempt))->assertOk()->assertInertia(fn (Assert $page) => $page->where('result.show_correct_answers', false)->missing('result.questions.0.correct_option_ids')->missing('result.questions.0.options')->missing('result.questions.0.is_correct')->missing('result.questions.0.feedback'));
        $this->actingAs(User::factory()->create())->get(route('assessments.my.results', $attempt))->assertForbidden();
    }

    public function test_retake_increments_attempt_and_preserves_previous_attempt_and_limit(): void
    {
        [$employee, $assignment] = $this->context(['allow_retake' => true, 'maximum_attempts' => 2, 'passing_score' => 100]);
        $question = $this->question($assignment->assessment, 'Retake');
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $first = AssessmentAttempt::query()->firstOrFail();
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $first));
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $second = AssessmentAttempt::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $second->attempt_number);
        $this->assertSame('failed', $first->fresh()->status);
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $second));
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertSessionHasErrors('attempt');
        $this->assertDatabaseCount('assessment_attempts', 2);
    }

    public function test_employee_can_flag_unflag_and_refresh_owned_attempt_without_affecting_scoring(): void
    {
        [$employee, $assignment] = $this->context();
        $question = $this->question($assignment->assessment, 'Review me');
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.flag', $attempt), ['question_id' => $question->id, 'flagged' => true])->assertOk();
        $this->assertSame([$question->id], $attempt->fresh()->flagged_question_ids);
        $this->actingAs($employee)->get(route('assessments.my.attempts.show', $attempt))->assertInertia(fn ($page) => $page->where('attempt.flagged_question_ids.0', $question->id));
        $this->actingAs(User::factory()->create())->putJson(route('assessments.my.attempts.flag', $attempt), ['question_id' => $question->id, 'flagged' => false])->assertForbidden();
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.flag', $attempt), ['question_id' => $question->id, 'flagged' => false])->assertOk();
        $this->assertSame([], $attempt->fresh()->flagged_question_ids);
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt));
        $this->assertSame('failed', $attempt->fresh()->status);
    }

    public function test_my_assessments_reports_due_labels_and_required_training_progress_only(): void
    {
        Carbon::setTestNow('2026-08-13 10:00:00');
        [$employee, $assignment] = $this->context();
        $assignment->update(['due_date' => now()->addDay()]);
        $admin = User::factory()->create(['role' => 'admin']);
        $required = AssessmentTrainingMaterial::query()->create(['assessment_id' => $assignment->assessment_id, 'title' => 'Required', 'type' => 'written', 'content' => 'R', 'is_required' => true, 'display_order' => 1, 'is_active' => true, 'created_by' => $admin->id]);
        AssessmentTrainingMaterial::query()->create(['assessment_id' => $assignment->assessment_id, 'title' => 'Optional', 'type' => 'written', 'content' => 'O', 'is_required' => false, 'display_order' => 2, 'is_active' => true, 'created_by' => $admin->id]);
        AssessmentTrainingProgress::query()->create(['material_id' => $required->id, 'employee_id' => $employee->id, 'started_at' => now(), 'completion_percentage' => 100, 'completed_at' => now()]);
        $this->actingAs($employee)->get(route('assessments.index'))->assertInertia(fn ($page) => $page->where('assignments.0.due_label', 'Due Tomorrow')->where('assignments.0.required_training_total', 1)->where('assignments.0.required_training_completed', 1)->where('assignments.0.training_complete', true));
        Carbon::setTestNow();
    }

    public function test_due_label_uses_the_philippine_calendar_date_at_utc_boundary(): void
    {
        Carbon::setTestNow('2026-08-13 16:30:00');
        [$employee, $assignment] = $this->context();
        $assignment->update(['due_date' => Carbon::parse('2026-08-14 15:00:00')]);

        $this->actingAs($employee)->get(route('assessments.index'))->assertInertia(fn ($page) => $page
            ->where('assignments.0.due_label', 'Due Today'));
        Carbon::setTestNow();
    }

    public function test_assessment_completed_before_deadline_does_not_later_display_as_overdue(): void
    {
        Carbon::setTestNow('2026-08-13 01:00:00');
        [$employee, $assignment] = $this->context(['maximum_attempts' => 2]);
        $due = now()->addDay();
        $assignment->update(['due_date' => $due]);
        AssessmentAttempt::query()->create([
            'assessment_id' => $assignment->assessment_id,
            'employee_id' => $employee->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 2,
            'question_snapshot' => [],
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'status' => 'failed',
            'percentage' => 50,
            'passed' => false,
        ]);

        Carbon::setTestNow('2026-08-20 01:00:00');

        $this->actingAs($employee)->get(route('assessments.index'))->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.status', 'failed')
            ->where('assignments.0.due_label', null));
        Carbon::setTestNow();
    }

    public function test_assignment_availability_override_replaces_future_assessment_date(): void
    {
        Carbon::setTestNow('2026-08-13 01:00:00');
        [$employee, $assignment] = $this->context(['available_at' => now()->addDays(2)]);
        $this->question($assignment->assessment, 'Available override question');
        $assignment->update(['available_from' => now()->subMinute()]);

        $this->actingAs($employee)->get(route('assessments.index'))->assertInertia(fn ($page) => $page
            ->where('assignments.0.status', 'ready')
            ->where('assignments.0.available_from', '2026-08-13T00:59:00+00:00'));
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertRedirect();
        $this->assertDatabaseHas('assessment_attempts', ['assignment_id' => $assignment->id, 'status' => 'in_progress']);
        Carbon::setTestNow();
    }

    public function test_pending_review_result_does_not_present_a_final_pass_or_score(): void
    {
        [$employee, $assignment] = $this->context();
        $short = AssessmentQuestion::query()->create(['assessment_id' => $assignment->assessment_id, 'question_text' => 'Explain', 'question_type' => 'short_answer', 'points' => 2, 'display_order' => 1, 'is_required' => true]);
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment));
        $attempt = AssessmentAttempt::query()->firstOrFail();
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), ['question_id' => $short->id, 'answer' => ['text' => 'Response']]);
        $this->actingAs($employee)->post(route('assessments.my.attempts.submit', $attempt));
        $this->actingAs($employee)->get(route('assessments.my.results', $attempt))->assertInertia(fn ($page) => $page->where('result.status', 'pending_review')->where('result.passed', null)->where('result.score', null)->where('result.percentage', null));
    }

    private function context(array $settings = []): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $assessment = Assessment::query()->create(array_merge(['title' => 'Week 1', 'passing_score' => 80, 'maximum_attempts' => 1, 'status' => 'published', 'published_at' => now(), 'created_by' => $admin->id], $settings));
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);

        return [$employee, $assignment];
    }

    private function question(Assessment $assessment, string $text, string $type = 'multiple_choice', ?int $skill = null, int $correct = 1): AssessmentQuestion
    {
        $question = AssessmentQuestion::query()->create(['assessment_id' => $assessment->id, 'skill_id' => $skill, 'question_text' => $text, 'question_type' => $type, 'points' => 2, 'display_order' => $assessment->questions()->count() + 1, 'is_required' => true]);
        $question->options()->createMany([['option_text' => 'A', 'is_correct' => true, 'display_order' => 1], ['option_text' => 'B', 'is_correct' => $correct === 2, 'display_order' => 2], ['option_text' => 'C', 'is_correct' => false, 'display_order' => 3]]);

        return $question;
    }

    private function save(User $employee, AssessmentAttempt $attempt, AssessmentQuestion $question, array $ids, int $status = 200): void
    {
        $this->actingAs($employee)->putJson(route('assessments.my.attempts.autosave', $attempt), ['question_id' => $question->id, 'answer' => ['option_ids' => $ids]])->assertStatus($status);
    }
}
