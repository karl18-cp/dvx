<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestionOption;
use App\Models\AssessmentSkill;
use App\Models\AssessmentTrainingMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_employee_can_open_employee_assessment_entry_point(): void
    {
        $employee = User::factory()->create(['role' => 'agent']);

        $this->actingAs($employee)->get(route('assessments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('assessments/index'));
    }

    public function test_manager_can_create_update_preview_publish_and_archive_assessment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = AssessmentCategory::query()->create(['name' => 'Compliance']);
        $data = $this->validData($category->id);

        $this->actingAs($admin)->post(route('assessments.store'), $data)
            ->assertRedirect(route('assessments.manage'));

        $assessment = Assessment::query()->firstOrFail();
        $this->assertSame('draft', $assessment->status);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'assessment.created', 'target_id' => $assessment->id]);

        $this->actingAs($admin)->put(route('assessments.update', $assessment), [...$data, 'title' => 'Updated Compliance'])
            ->assertRedirect(route('assessments.manage'));
        $this->actingAs($admin)->get(route('assessments.preview', $assessment))->assertOk();
        $this->actingAs($admin)->patch(route('assessments.publish', $assessment))->assertRedirect(route('assessments.manage'));
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'published']);
        $this->actingAs($admin)->patch(route('assessments.archive', $assessment))->assertRedirect(route('assessments.manage'));
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'archived']);
    }

    public function test_employee_cannot_access_assessment_management_endpoints(): void
    {
        $employee = User::factory()->create(['role' => 'agent']);

        $this->actingAs($employee)->get(route('assessments.manage'))->assertForbidden();
        $this->actingAs($employee)->post(route('assessments.store'), $this->validData())->assertForbidden();
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_clone_creates_clean_draft_with_questions_options_skills_and_shared_media_reference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $assessment = Assessment::query()->create([...$this->validData(), 'title' => 'Week 3', 'status' => 'published', 'created_by' => $admin->id]);
        $skill = AssessmentSkill::query()->create(['name' => 'Calls']);
        $question = $assessment->questions()->create(['skill_id' => $skill->id, 'question_text' => 'Choose', 'question_type' => 'multiple_choice', 'points' => 2, 'feedback' => 'Review', 'display_order' => 1, 'is_required' => true]);
        $question->options()->createMany([['option_text' => 'A', 'is_correct' => true, 'display_order' => 1], ['option_text' => 'B', 'is_correct' => false, 'display_order' => 2]]);
        AssessmentTrainingMaterial::query()->create(['assessment_id' => $assessment->id, 'title' => 'Video', 'type' => 'video', 'storage_disk' => 'local', 'storage_key' => 'assessment-training/shared.mp4', 'original_filename' => 'shared.mp4', 'stored_filename' => 'shared.mp4', 'mime_type' => 'video/mp4', 'file_size' => 100, 'is_required' => true, 'display_order' => 1, 'is_active' => true, 'created_by' => $admin->id]);
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);
        AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now(), 'status' => 'in_progress']);

        $this->actingAs($admin)->post(route('assessments.clone', $assessment))->assertRedirect();

        $clone = Assessment::query()->where('title', 'Copy of Week 3')->firstOrFail();
        $this->assertSame('draft', $clone->status);
        $this->assertNull($clone->published_at);
        $this->assertSame($skill->id, $clone->questions()->firstOrFail()->skill_id);
        $this->assertSame(['A', 'B'], $clone->questions()->firstOrFail()->options()->pluck('option_text')->all());
        $this->assertSame('assessment-training/shared.mp4', $clone->trainingMaterials()->value('storage_key'));
        $this->assertCount(1, Assessment::query()->findOrFail($assessment->id)->assignments);
        $this->assertSame(0, $clone->assignments()->count());
        $this->assertSame(0, $clone->attempts()->count());
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Assessment Cloned', 'target_id' => $clone->id]);
    }

    public function test_archive_search_category_and_created_date_are_server_paginated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = AssessmentCategory::query()->create(['name' => 'Calls']);
        foreach (range(1, 16) as $number) {
            Assessment::query()->create([...$this->validData($category->id), 'title' => "Archived Calls {$number}", 'status' => 'archived', 'created_by' => $admin->id]);
        }
        Assessment::query()->create([...$this->validData(), 'title' => 'Unrelated', 'status' => 'archived', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('assessments.manage', ['status' => 'archived', 'search' => 'Archived Calls', 'category' => $category->id, 'from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->where('assessments.total', 16)->has('assessments.data', 15));
    }

    public function test_clone_rolls_back_if_a_nested_record_cannot_be_created(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assessment = Assessment::query()->create([...$this->validData(), 'title' => 'Rollback Source', 'status' => 'published', 'created_by' => $admin->id]);
        $question = $assessment->questions()->create(['question_text' => 'Choose', 'question_type' => 'multiple_choice', 'points' => 1, 'display_order' => 1, 'is_required' => true]);
        $question->options()->create(['option_text' => 'A', 'is_correct' => true, 'display_order' => 1]);
        AssessmentQuestionOption::creating(fn () => throw new \RuntimeException('Simulated nested clone failure'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('assessments.clone', $assessment));
            $this->fail('The simulated clone failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated nested clone failure', $exception->getMessage());
        } finally {
            AssessmentQuestionOption::getEventDispatcher()?->forget('eloquent.creating: '.AssessmentQuestionOption::class);
        }

        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseCount('assessment_questions', 1);
        $this->assertDatabaseCount('assessment_question_options', 1);
        $this->assertDatabaseMissing('assessment_activity_logs', ['action' => 'Assessment Cloned']);
    }

    public function test_management_completion_counts_only_passed_and_failed_as_finalized(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assessment = Assessment::query()->create([...$this->validData(), 'status' => 'published', 'created_by' => $admin->id]);
        foreach (['assigned', 'in_progress', 'pending_review', 'passed', 'failed'] as $status) {
            $employee = User::factory()->create(['role' => 'agent']);
            AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => $status]);
        }

        $this->actingAs($admin)->get(route('assessments.manage'))->assertInertia(fn (Assert $page) => $page
            ->where('assessments.data.0.assignments_count', 5)
            ->where('assessments.data.0.assigned_count', 1)
            ->where('assessments.data.0.in_progress_count', 1)
            ->where('assessments.data.0.pending_review_count', 1)
            ->where('assessments.data.0.passed_count', 1)
            ->where('assessments.data.0.failed_count', 1));
    }

    private function validData(?int $categoryId = null): array
    {
        return [
            'title' => 'Data Privacy', 'description' => 'Annual refresher', 'instructions' => 'Complete all sections.',
            'category_id' => $categoryId, 'difficulty' => 'beginner', 'passing_score' => 80,
            'time_limit_minutes' => 30, 'maximum_attempts' => 2, 'available_at' => null, 'due_at' => null,
            'allow_retake' => true, 'randomize_questions' => true, 'randomize_answers' => true,
            'show_correct_answers' => false,
        ];
    }
}
