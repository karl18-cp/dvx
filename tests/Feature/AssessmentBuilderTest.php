<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentSkill;
use App\Models\AssessmentTrainingMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_written_and_uploaded_training_material(): void
    {
        Storage::fake('local');
        [$admin,$assessment] = $this->context();
        $this->actingAs($admin)->post(route('assessments.materials.store', $assessment), ['title' => 'Policy', 'type' => 'written', 'content' => 'Safe plain-text lesson', 'is_required' => true, 'required_completion_percentage' => 90])->assertRedirect();
        $this->actingAs($admin)->post(route('assessments.materials.store', $assessment), ['title' => 'Video', 'type' => 'video', 'file' => UploadedFile::fake()->create('training.mp4', 100, 'video/mp4'), 'is_required' => true, 'required_completion_percentage' => 95])->assertRedirect();
        $material = AssessmentTrainingMaterial::query()->where('type', 'video')->firstOrFail();
        Storage::disk('local')->assertExists($material->storage_key);
    }

    public function test_executable_upload_is_rejected(): void
    {
        Storage::fake('local');
        [$admin,$assessment] = $this->context();
        $this->actingAs($admin)->post(route('assessments.materials.store', $assessment), ['title' => 'Bad', 'type' => 'video', 'file' => UploadedFile::fake()->create('bad.php', 10, 'application/x-php'), 'is_required' => true, 'required_completion_percentage' => 90])->assertSessionHasErrors('file');
    }

    public function test_question_options_are_validated_and_saved_transactionally(): void
    {
        [$admin,$assessment] = $this->context();
        $skill = AssessmentSkill::query()->create(['name' => 'Grammar']);
        $data = ['question_text' => 'Choose one', 'question_type' => 'multiple_choice', 'points' => 2, 'skill_id' => $skill->id, 'feedback' => 'Review', 'is_required' => true, 'options' => [['option_text' => 'A', 'is_correct' => true], ['option_text' => 'B', 'is_correct' => false]]];
        $this->actingAs($admin)->post(route('assessments.questions.store', $assessment), $data)->assertRedirect();
        $this->assertDatabaseCount('assessment_questions', 1);
        $this->assertDatabaseCount('assessment_question_options', 2);
        $this->actingAs($admin)->post(route('assessments.questions.store', $assessment), [...$data, 'options' => [['option_text' => 'A', 'is_correct' => true], ['option_text' => 'B', 'is_correct' => true]]])->assertSessionHasErrors('options');
        $this->assertDatabaseCount('assessment_questions', 1);
    }

    public function test_employee_cannot_manage_builder(): void
    {
        [$admin,$assessment] = $this->context();
        $employee = User::factory()->create(['role' => 'agent']);
        $this->actingAs($employee)->get(route('assessments.builder', $assessment))->assertForbidden();
        $this->actingAs($employee)->post(route('assessments.questions.store', $assessment), [])->assertForbidden();
    }

    public function test_true_false_requires_exact_labels_and_one_correct_answer(): void
    {
        [$admin, $assessment] = $this->context();
        $base = ['question_text' => 'Is this true?', 'question_type' => 'true_false', 'points' => 1, 'is_required' => true];

        $this->actingAs($admin)->post(route('assessments.questions.store', $assessment), [...$base, 'options' => [
            ['option_text' => 'True', 'is_correct' => true],
            ['option_text' => 'False', 'is_correct' => false],
        ]])->assertRedirect();
        $this->assertDatabaseHas('assessment_question_options', ['option_text' => 'True', 'is_correct' => true]);
        $this->assertDatabaseHas('assessment_question_options', ['option_text' => 'False', 'is_correct' => false]);

        foreach ([
            [['option_text' => 'True', 'is_correct' => true], ['option_text' => 'False', 'is_correct' => false], ['option_text' => 'Maybe', 'is_correct' => false]],
            [['option_text' => 'True', 'is_correct' => true], ['option_text' => 'True', 'is_correct' => false]],
            [['option_text' => 'True', 'is_correct' => false], ['option_text' => 'False', 'is_correct' => false]],
            [['option_text' => 'True', 'is_correct' => true], ['option_text' => 'False', 'is_correct' => true]],
        ] as $invalidOptions) {
            $this->actingAs($admin)->post(route('assessments.questions.store', $assessment), [...$base, 'options' => $invalidOptions])->assertSessionHasErrors('options');
        }
        $this->assertDatabaseCount('assessment_questions', 1);
    }

    public function test_true_false_edit_normalizes_order_preserves_false_and_does_not_change_snapshot(): void
    {
        [$admin, $assessment] = $this->context();
        $employee = User::factory()->create(['role' => 'agent']);
        $question = $assessment->questions()->create(['question_text' => 'Legacy', 'question_type' => 'true_false', 'points' => 1, 'display_order' => 1, 'is_required' => true]);
        $question->options()->createMany([
            ['option_text' => 'False', 'is_correct' => true, 'display_order' => 1],
            ['option_text' => 'True', 'is_correct' => false, 'display_order' => 2],
        ]);
        $assignment = $assessment->assignments()->create(['employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);
        $snapshot = [['id' => $question->id, 'type' => 'true_false', 'options' => [['text' => 'Legacy False']]]];
        $attempt = AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'question_snapshot' => $snapshot, 'started_at' => now(), 'status' => 'in_progress']);

        $this->actingAs($admin)->put(route('assessments.questions.update', [$assessment, $question]), [
            'question_text' => 'Updated', 'question_type' => 'true_false', 'points' => 1, 'is_required' => true,
            'options' => [['option_text' => 'False', 'is_correct' => true], ['option_text' => 'True', 'is_correct' => false]],
        ])->assertRedirect();

        $this->assertSame(['True', 'False'], $question->options()->orderBy('display_order')->pluck('option_text')->all());
        $this->assertSame('False', $question->options()->where('is_correct', true)->value('option_text'));
        $this->assertSame($snapshot, $attempt->fresh()->question_snapshot);
    }

    private function context(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assessment = Assessment::query()->create(['title' => 'Training', 'difficulty' => 'beginner', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'draft', 'created_by' => $admin->id]);

        return [$admin, $assessment];
    }
}
