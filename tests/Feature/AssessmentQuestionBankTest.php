<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentBankQuestion;
use App\Models\AssessmentRandomPool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_true_false_bank_question_and_employee_cannot_access_bank(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $employee = User::factory()->create(['role' => 'agent']);
        $payload = ['question_text' => 'Is this true?', 'question_type' => 'true_false', 'difficulty' => 'easy', 'points' => 1, 'options' => [['option_text' => 'True', 'is_correct' => true], ['option_text' => 'False', 'is_correct' => false]]];
        $this->actingAs($manager)->post(route('assessments.question-bank.store'), $payload)->assertRedirect();
        $question = AssessmentBankQuestion::query()->with('options')->firstOrFail();
        $this->assertSame(['True', 'False'], $question->options->pluck('option_text')->all());
        $this->actingAs($employee)->get(route('assessments.question-bank'))->assertForbidden();
    }

    public function test_random_pool_snapshots_distinct_questions_and_bank_edits_do_not_mutate_attempt(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $assessment = Assessment::query()->create(['title' => 'Random', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        foreach (range(1, 3) as $number) {
            $bank = AssessmentBankQuestion::query()->create(['question_text' => "Question {$number}", 'question_type' => 'multiple_choice', 'difficulty' => 'medium', 'points' => 1, 'status' => 'active', 'created_by' => $admin->id]);
            $bank->options()->createMany([['option_text' => 'A', 'is_correct' => true, 'display_order' => 1], ['option_text' => 'B', 'is_correct' => false, 'display_order' => 2]]);
        }
        AssessmentRandomPool::query()->create(['assessment_id' => $assessment->id, 'difficulty' => 'medium', 'questions_to_select' => 2, 'points_per_question' => 2, 'display_order' => 1]);
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);
        $this->actingAs($employee)->post(route('assessments.my.start', $assignment))->assertRedirect();
        $attempt = $assignment->attempts()->firstOrFail();
        $this->assertCount(2, $attempt->question_snapshot);
        $this->assertCount(2, collect($attempt->question_snapshot)->pluck('source_question_bank_id')->unique());
        $original = $attempt->question_snapshot[0]['text'];
        AssessmentBankQuestion::query()->findOrFail($attempt->question_snapshot[0]['source_question_bank_id'])->update(['question_text' => 'Edited later']);
        $this->assertSame($original, $attempt->fresh()->question_snapshot[0]['text']);
    }

    public function test_pool_rejects_more_questions_than_are_available_and_archive_excludes_source(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assessment = Assessment::query()->create(['title' => 'Pool', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'draft', 'created_by' => $admin->id]);
        AssessmentBankQuestion::query()->create(['question_text' => 'Archived', 'question_type' => 'short_answer', 'difficulty' => 'hard', 'points' => 1, 'status' => 'archived', 'created_by' => $admin->id]);
        $this->actingAs($admin)
            ->from(route('assessments.builder', $assessment))
            ->post(route('assessments.random-pools.store', $assessment), [
                'difficulty' => 'hard',
                'questions_to_select' => 1,
                'points_per_question' => 1,
            ])
            ->assertRedirect(route('assessments.builder', $assessment))
            ->assertSessionHasErrors('questions_to_select');
        $this->assertDatabaseCount('assessment_random_pools', 0);
    }
}
