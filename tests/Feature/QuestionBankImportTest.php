<?php

namespace Tests\Feature;

use App\Models\AssessmentBankQuestion;
use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankImportTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $category = AssessmentCategory::query()->create(['name' => 'Import Category', 'is_active' => true]);
        $skill = AssessmentSkill::query()->create(['name' => 'Import Skill', 'is_active' => true]);

        return ['category_id' => $category->id, 'skill_id' => $skill->id, 'applies_to_all_campaigns' => true, 'campaign_ids' => [], 'questions' => [
            ['question_text' => 'Choose a greeting.', 'question_type' => 'multiple_choice', 'points' => 1, 'difficulty' => 'medium', 'options' => [
                ['option_text' => 'Hello', 'is_correct' => true], ['option_text' => 'Go away', 'is_correct' => false],
            ]],
            ['question_text' => 'Explain empathy.', 'question_type' => 'short_answer', 'points' => 2, 'difficulty' => 'easy', 'options' => []],
        ]];
    }

    public function test_selected_category_and_skill_override_file_metadata(): void
    {
        $data = $this->data();
        $data['questions'][0]['category_id'] = 99999;
        $data['questions'][0]['skill_id'] = 99999;
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('assessments.question-bank.import'), $data)->assertRedirect();
        $this->assertDatabaseCount('assessment_bank_questions', 2);
        foreach (AssessmentBankQuestion::all() as $question) {
            $this->assertSame($data['category_id'], $question->category_id);
            $this->assertSame($data['skill_id'], $question->skill_id);
        }
        $this->assertDatabaseCount('assessment_bank_question_options', 2);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Question Bank Question Imported']);
    }

    public function test_destination_is_required_and_invalid_questions_prevent_partial_import(): void
    {
        $data = $this->data();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('assessments.question-bank.import'), [...$data, 'category_id' => '', 'skill_id' => ''])->assertSessionHasErrors(['category_id', 'skill_id']);
        $data['questions'][1] = $data['questions'][0];
        $data['questions'][1]['options'][0]['is_correct'] = false;
        $this->post(route('assessments.question-bank.import'), $data)->assertSessionHasErrors('questions.1.options');
        $this->assertDatabaseCount('assessment_bank_questions', 0);
    }

    public function test_employee_cannot_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'agent']))->post(route('assessments.question-bank.import'), $this->data())->assertForbidden();
        $this->assertDatabaseCount('assessment_bank_questions', 0);
    }

    public function test_inactive_skills_and_large_batches_are_rejected(): void
    {
        $data = $this->data();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('assessments.question-bank.import'), [...$data, 'questions' => array_fill(0, 101, $data['questions'][0])])->assertSessionHasErrors('questions');
        AssessmentSkill::query()->whereKey($data['skill_id'])->update(['is_active' => false]);
        $this->post(route('assessments.question-bank.import'), $data)->assertSessionHasErrors('skill_id');
        $this->assertDatabaseCount('assessment_bank_questions', 0);
    }
}
