<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentBankQuestion;
use App\Models\Campaign;
use App\Models\TrainingLibraryMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SimpleAssessmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_simple_create_but_employee_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);

        $this->actingAs($admin)->get(route('assessments.simple.create'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('assessments/simple-create'));
        $this->actingAs($employee)->get(route('assessments.simple.create'))->assertForbidden();
    }

    public function test_create_generates_draft_slots_with_deterministic_exact_points(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Customer Support', 'abbreviation' => 'CS', 'is_active' => true]);

        $this->actingAs($admin)->post(route('assessments.simple.store'), $this->payload($campaign->id, 7, 100))->assertRedirect();

        $assessment = Assessment::query()->firstOrFail();
        $this->assertTrue($assessment->simple_builder_enabled);
        $this->assertSame('draft', $assessment->status);
        $this->assertSame([15.0, 15.0, 14.0, 14.0, 14.0, 14.0, 14.0], $assessment->questions()->orderBy('display_order')->pluck('points')->map(fn ($points) => (float) $points)->all());
        $this->assertSame(100.0, (float) $assessment->questions()->sum('points'));
        $this->assertTrue($assessment->campaigns()->whereKey($campaign->id)->exists());
    }

    public function test_invalid_setup_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Sales', 'abbreviation' => 'SAL', 'is_active' => true]);

        $this->actingAs($admin)->from(route('assessments.simple.create'))->post(route('assessments.simple.store'), $this->payload($campaign->id, 10, 5))
            ->assertSessionHasErrors('total_points');
        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_title_campaign_and_compact_setup_fields_are_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->post(route('assessments.simple.store'), [
            'training_source' => 'none', 'training_required' => true,
            'question_count' => 0, 'total_points' => 0, 'passing_score' => 101,
            'time_limit_minutes' => 0, 'maximum_attempts' => 4,
            'applies_to_all_campaigns' => false, 'campaign_ids' => [],
        ]);

        $response->assertSessionHasErrors(['title', 'campaign_ids', 'question_count', 'total_points', 'passing_score', 'time_limit_minutes', 'maximum_attempts']);
    }

    public function test_training_library_and_secure_upload_integrate_with_required_setting(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Training Flow', 'abbreviation' => 'TF', 'is_active' => true]);
        $library = TrainingLibraryMaterial::query()->create(['title' => 'Library Lesson', 'type' => 'written', 'content' => 'Review me', 'status' => 'active', 'created_by' => $admin->id, 'applies_to_all_campaigns' => true]);

        $this->actingAs($admin)->post(route('assessments.simple.store'), [...$this->payload($campaign->id, 1, 10), 'title' => 'Library Assessment', 'training_source' => 'library', 'library_material_id' => $library->id, 'training_required' => false])->assertRedirect();
        $libraryAssessment = Assessment::query()->where('title', 'Library Assessment')->firstOrFail();
        $this->assertDatabaseHas('assessment_training_attachments', ['assessment_id' => $libraryAssessment->id, 'library_material_id' => $library->id, 'is_required' => false]);

        $this->actingAs($admin)->post(route('assessments.simple.store'), [...$this->payload($campaign->id, 1, 10), 'title' => 'Upload Assessment', 'training_source' => 'upload', 'training_title' => 'Uploaded Guide', 'training_type' => 'document', 'training_file' => UploadedFile::fake()->create('guide.pdf', 20, 'application/pdf'), 'training_required' => true])->assertRedirect();
        $upload = Assessment::query()->where('title', 'Upload Assessment')->firstOrFail()->trainingMaterials()->firstOrFail();
        $this->assertTrue($upload->is_required);
        Storage::disk($upload->storage_disk)->assertExists($upload->storage_key);
    }

    public function test_all_four_simple_question_types_save_with_existing_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Question Types', 'abbreviation' => 'QT', 'is_active' => true]);
        $this->actingAs($admin)->post(route('assessments.simple.store'), $this->payload($campaign->id, 4, 40));
        $assessment = Assessment::query()->firstOrFail();
        $payloads = [
            ['question_type' => 'multiple_choice', 'options' => [['option_text' => 'A', 'is_correct' => true], ['option_text' => 'B', 'is_correct' => false]]],
            ['question_type' => 'true_false', 'options' => [['option_text' => 'True', 'is_correct' => true], ['option_text' => 'False', 'is_correct' => false]]],
            ['question_type' => 'multiple_selection', 'options' => [['option_text' => 'A', 'is_correct' => true], ['option_text' => 'B', 'is_correct' => true]]],
            ['question_type' => 'short_answer', 'options' => []],
        ];
        foreach ($assessment->questions()->orderBy('display_order')->get() as $index => $question) {
            $this->actingAs($admin)->put(route('assessments.questions.update', [$assessment, $question]), [...$payloads[$index], 'question_text' => 'Question '.($index + 1), 'points' => 10, 'skill_id' => null, 'feedback' => 'Guidance', 'is_required' => true])->assertRedirect();
        }
        $this->assertSame(['multiple_choice', 'true_false', 'multiple_selection', 'short_answer'], $assessment->questions()->orderBy('display_order')->pluck('question_type')->all());
    }

    public function test_incomplete_simple_assessment_cannot_publish(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Retention', 'abbreviation' => 'RET', 'is_active' => true]);
        $this->actingAs($admin)->post(route('assessments.simple.store'), $this->payload($campaign->id, 2, 10));
        $assessment = Assessment::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('assessments.publish', $assessment))
            ->assertRedirect(route('assessments.simple.review', $assessment))->assertSessionHasErrors('publish');
        $this->assertSame('draft', $assessment->fresh()->status);
    }

    public function test_completed_simple_assessment_can_publish_and_clone_to_simple_builder(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Technical', 'abbreviation' => 'TECH', 'is_active' => true]);
        $this->actingAs($admin)->post(route('assessments.simple.store'), $this->payload($campaign->id, 1, 10));
        $assessment = Assessment::query()->firstOrFail();
        $question = $assessment->questions()->firstOrFail();
        $question->update(['question_text' => 'PHP is a server-side language.']);
        $question->options()->createMany([
            ['option_text' => 'True', 'is_correct' => true, 'display_order' => 1],
            ['option_text' => 'False', 'is_correct' => false, 'display_order' => 2],
        ]);

        $this->actingAs($admin)->patch(route('assessments.publish', $assessment))->assertRedirect(route('assessments.manage'));
        $this->assertSame('published', $assessment->fresh()->status);
        $this->actingAs($admin)->post(route('assessments.clone', $assessment))->assertRedirect();
        $clone = Assessment::query()->where('title', 'Copy of Simple Workflow')->firstOrFail();
        $this->assertTrue($clone->simple_builder_enabled);
        $this->assertSame('draft', $clone->status);
    }

    public function test_manager_can_fill_multiple_slots_from_question_bank_at_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Bulk Bank', 'abbreviation' => 'BB', 'is_active' => true]);
        $this->actingAs($admin)->post(route('assessments.simple.store'), $this->payload($campaign->id, 3, 30));
        $assessment = Assessment::query()->firstOrFail();
        $banks = collect(['First bank question', 'Second bank question'])->map(function (string $text) use ($admin) {
            $bank = AssessmentBankQuestion::query()->create(['question_text' => $text, 'question_type' => 'multiple_choice', 'difficulty' => 'medium', 'points' => 1, 'status' => 'active', 'created_by' => $admin->id, 'applies_to_all_campaigns' => true]);
            $bank->options()->createMany([['option_text' => 'Correct', 'is_correct' => true, 'display_order' => 1], ['option_text' => 'Incorrect', 'is_correct' => false, 'display_order' => 2]]);

            return $bank;
        });

        $this->actingAs($admin)->post(route('assessments.simple.bank.use-many', $assessment), [
            'bank_question_ids' => $banks->pluck('id')->all(),
            'starting_question_id' => $assessment->questions()->orderBy('display_order')->value('id'),
        ])->assertRedirect(route('assessments.simple.builder', $assessment));

        $this->assertSame(['First bank question', 'Second bank question', ''], $assessment->questions()->orderBy('display_order')->pluck('question_text')->all());
        $this->assertSame([10.0, 10.0, 10.0], $assessment->questions()->orderBy('display_order')->pluck('points')->map(fn ($points) => (float) $points)->all());
        $this->assertSame([2, 2, 0], $assessment->questions()->orderBy('display_order')->withCount('options')->pluck('options_count')->all());
    }

    public function test_question_bank_shortcut_filters_by_campaign_and_current_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Filtered Bank', 'abbreviation' => 'FB', 'is_active' => true]);
        $this->actingAs($admin)->post(route('assessments.simple.store'), $this->payload($campaign->id, 1, 10));
        $assessment = Assessment::query()->firstOrFail();
        AssessmentBankQuestion::query()->create(['question_text' => 'Matching MC', 'question_type' => 'multiple_choice', 'difficulty' => 'medium', 'points' => 1, 'status' => 'active', 'created_by' => $admin->id, 'applies_to_all_campaigns' => true]);
        AssessmentBankQuestion::query()->create(['question_text' => 'Wrong Type', 'question_type' => 'short_answer', 'difficulty' => 'medium', 'points' => 1, 'status' => 'active', 'created_by' => $admin->id, 'applies_to_all_campaigns' => true]);

        $this->actingAs($admin)->get(route('assessments.simple.bank', [$assessment, 'type' => 'multiple_choice']))
            ->assertInertia(fn (Assert $page) => $page->where('questions.total', 1)->where('questions.data.0.question_text', 'Matching MC'));
    }

    public function test_ready_assessment_review_and_schedule_use_existing_schedule_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Schedule Flow', 'abbreviation' => 'SF', 'is_active' => true]);
        $this->actingAs($admin)->post(route('assessments.simple.store'), $this->payload($campaign->id, 1, 10));
        $assessment = Assessment::query()->firstOrFail();
        $question = $assessment->questions()->firstOrFail();
        $question->update(['question_text' => 'Ready question']);
        $question->options()->createMany([['option_text' => 'A', 'is_correct' => true, 'display_order' => 1], ['option_text' => 'B', 'is_correct' => false, 'display_order' => 2]]);

        $this->actingAs($admin)->get(route('assessments.simple.review', $assessment))->assertOk()->assertInertia(fn (Assert $page) => $page->where('readinessErrors', []));
        $publishAt = now('Asia/Manila')->addDay()->format('Y-m-d\TH:i');
        $this->actingAs($admin)->patch(route('assessments.simple.schedule.save', $assessment), ['publish_at' => $publishAt, 'available_at' => null, 'due_at' => null])->assertRedirect(route('assessments.manage'));
        $this->assertNotNull($assessment->fresh()->publish_at);
        $this->assertSame('draft', $assessment->fresh()->status);
    }

    private function payload(int $campaignId, int $questions, int $points): array
    {
        return [
            'title' => 'Simple Workflow', 'description' => 'Focused setup',
            'applies_to_all_campaigns' => false, 'campaign_ids' => [$campaignId],
            'training_source' => 'none', 'training_required' => false,
            'question_count' => $questions, 'total_points' => $points,
            'passing_score' => 80, 'time_limit_minutes' => 15, 'maximum_attempts' => 2,
        ];
    }
}
