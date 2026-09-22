<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentBankQuestion;
use App\Models\AssessmentRandomPool;
use App\Models\AssessmentTrainingAttachment;
use App\Models\AssessmentTrainingAttachmentProgress;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TrainingLibraryMaterial;
use App\Models\User;
use App\Services\AssessmentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_training_library_for_an_assessment(): void
    {
        [$admin, $ibp, $hi] = $this->people();
        $assessment = $this->assessment($admin, [$ibp->id]);
        $this->material($admin, 'IBP Library Material', $ibp);
        $this->material($admin, 'HI Library Material', $hi);

        $this->actingAs($admin)->get(route('training-library.index', ['assessment' => $assessment->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessments/training-library')
                ->where('target_assessment_id', $assessment->id)
                ->where('materials.total', 1)
                ->where('materials.data.0.title', 'IBP Library Material'));
    }

    public function test_advanced_builder_pickers_redirect_back_after_bulk_add(): void
    {
        [$admin, $ibp] = $this->people();
        $assessment = $this->assessment($admin, [$ibp->id]);
        $material = $this->material($admin, 'Selected Training', $ibp);
        $question = $this->bankQuestion($admin, 'Selected Question', $ibp);

        $this->actingAs($admin)->post(route('training-library.attach', $assessment), [
            'material_ids' => [$material->id],
            'is_required' => true,
            'required_completion_percentage' => 90,
        ])->assertRedirect(route('assessments.builder', $assessment));

        $this->actingAs($admin)->get(route('assessments.builder', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.materials', 1)
                ->has('assessment.training_attachments', 1)
                ->where('assessment.training_attachments.0.material.title', 'Selected Training'));

        $this->actingAs($admin)->post(route('assessments.questions.from-bank', $assessment), [
            'question_ids' => [$question->id],
        ])->assertRedirect(route('assessments.builder', $assessment));

        $this->assertDatabaseHas('assessment_training_attachments', ['assessment_id' => $assessment->id, 'library_material_id' => $material->id]);
        $this->assertDatabaseHas('assessment_questions', ['assessment_id' => $assessment->id, 'source_bank_question_id' => $question->id]);
    }

    public function test_campaign_incompatible_training_is_inaccessible_and_multi_campaign_training_isolated(): void
    {
        [$admin, $ibp, $hi, $ibpEmployee, $hiEmployee] = $this->people();
        $assessment = $this->assessment($admin, [$ibp->id, $hi->id]);
        $hiMaterial = $this->material($admin, 'HI Training', $hi);
        $ibpMaterial = $this->material($admin, 'IBP Training', $ibp);
        $allMaterial = $this->material($admin, 'All Campaign Training');
        Storage::fake('local');
        foreach ([$hiMaterial, $ibpMaterial, $allMaterial] as $material) {
            $path = 'campaign-security/'.$material->id.'.txt';
            Storage::disk('local')->put($path, $material->title);
            $material->update(['type' => 'document', 'storage_disk' => 'local', 'storage_key' => $path, 'original_filename' => $material->id.'.txt', 'mime_type' => 'text/plain']);
        }
        $attachments = collect([$hiMaterial, $ibpMaterial, $allMaterial])->map(fn ($material, $index) => AssessmentTrainingAttachment::query()->create(['assessment_id' => $assessment->id, 'library_material_id' => $material->id, 'is_required' => true, 'required_completion_percentage' => 100, 'display_order' => $index + 1]));
        $ibpAssignment = $this->assignment($assessment, $ibpEmployee, $admin, $ibp);
        $hiAssignment = $this->assignment($assessment, $hiEmployee, $admin, $hi);

        $this->actingAs($ibpEmployee)->get(route('assessments.index'))->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.has_training', true)
            ->where('assignments.0.required_training_total', 2)
            ->where('assignments.0.required_training_completed', 0)
            ->where('assignments.0.training_complete', false)
            ->where('assignments.0.status', 'training_required'));

        $this->actingAs($ibpEmployee)->get(route('assessments.my.training', $ibpAssignment))->assertInertia(fn (Assert $page) => $page
            ->has('materials', 2)
            ->where('materials.0.title', 'IBP Training')
            ->where('materials.1.title', 'All Campaign Training'));
        $this->actingAs($hiEmployee)->get(route('assessments.my.training', $hiAssignment))->assertInertia(fn (Assert $page) => $page
            ->has('materials', 2)
            ->where('materials.0.title', 'HI Training')
            ->where('materials.1.title', 'All Campaign Training'));

        $this->actingAs($ibpEmployee)->post(route('assessments.my.library.open', [$ibpAssignment, $attachments[0]]), ['campaign_id' => $ibp->id])->assertNotFound();
        $this->actingAs($hiEmployee)->post(route('assessments.my.library.open', [$hiAssignment, $attachments[1]]), ['campaign_id' => $hi->id])->assertNotFound();
        $this->actingAs($ibpEmployee)->get(route('assessments.my.library.media', [$ibpAssignment, $attachments[0]]))->assertNotFound();
        $this->actingAs($hiEmployee)->get(route('assessments.my.library.media', [$hiAssignment, $attachments[1]]))->assertNotFound();
        $this->actingAs($ibpEmployee)->post(route('assessments.my.library.open', [$ibpAssignment, $attachments[2]]))->assertOk();
        $this->actingAs($hiEmployee)->post(route('assessments.my.library.open', [$hiAssignment, $attachments[2]]))->assertOk();
        $this->actingAs($ibpEmployee)->get(route('assessments.my.library.media', [$ibpAssignment, $attachments[2]]))->assertOk();
        $this->actingAs($hiEmployee)->get(route('assessments.my.library.media', [$hiAssignment, $attachments[2]]))->assertOk();

        foreach ([[$attachments[1], $ibpEmployee], [$attachments[2], $ibpEmployee], [$attachments[0], $hiEmployee], [$attachments[2], $hiEmployee]] as [$attachment, $employee]) {
            AssessmentTrainingAttachmentProgress::query()->updateOrCreate(['attachment_id' => $attachment->id, 'employee_id' => $employee->id], ['started_at' => now(), 'completion_percentage' => 100, 'completed_at' => now()]);
        }
        $service = app(AssessmentAttemptService::class);
        $this->assertTrue($service->requiredTrainingComplete($ibpAssignment));
        $this->assertTrue($service->requiredTrainingComplete($hiAssignment));
        $this->actingAs($ibpEmployee)->get(route('assessments.index'))->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.required_training_completed', 2)
            ->where('assignments.0.training_complete', true)
            ->where('assignments.0.status', 'ready'));
    }

    public function test_multi_campaign_random_pools_never_select_cross_campaign_questions(): void
    {
        [$admin, $ibp, $hi, $ibpEmployee, $hiEmployee] = $this->people();
        $assessment = $this->assessment($admin, [$ibp->id, $hi->id]);
        $hiQuestion = $this->bankQuestion($admin, 'HI Question', $hi);
        $ibpQuestion = $this->bankQuestion($admin, 'IBP Question', $ibp);
        $allQuestion = $this->bankQuestion($admin, 'All Campaign Question');
        AssessmentRandomPool::query()->create(['assessment_id' => $assessment->id, 'difficulty' => 'medium', 'questions_to_select' => 2, 'points_per_question' => 1, 'display_order' => 1]);

        $ibpAttempt = app(AssessmentAttemptService::class)->start($this->assignment($assessment, $ibpEmployee, $admin, $ibp), $ibpEmployee);
        $hiAttempt = app(AssessmentAttemptService::class)->start($this->assignment($assessment, $hiEmployee, $admin, $hi), $hiEmployee);
        $this->assertEqualsCanonicalizing([$ibpQuestion->id, $allQuestion->id], collect($ibpAttempt->question_snapshot)->pluck('source_question_bank_id')->all());
        $this->assertEqualsCanonicalizing([$hiQuestion->id, $allQuestion->id], collect($hiAttempt->question_snapshot)->pluck('source_question_bank_id')->all());
    }

    public function test_manipulated_identifiers_and_campaign_filters_do_not_broaden_authorization(): void
    {
        [$admin, $ibp, $hi, $ibpEmployee, $hiEmployee] = $this->people();
        $assessment = $this->assessment($admin, [$ibp->id]);
        $material = $this->material($admin, 'IBP Only', $ibp);
        $attachment = AssessmentTrainingAttachment::query()->create(['assessment_id' => $assessment->id, 'library_material_id' => $material->id, 'is_required' => false, 'required_completion_percentage' => 0, 'display_order' => 1]);
        $ibpAssignment = $this->assignment($assessment, $ibpEmployee, $admin, $ibp);

        $this->actingAs($hiEmployee)->post(route('assessments.my.library.open', [$ibpAssignment, $attachment]), ['employee_id' => $hiEmployee->id, 'campaign_id' => $hi->id, 'assessment_id' => $assessment->id, 'training_material_id' => $material->id])->assertForbidden();
        $this->actingAs($hiEmployee)->get(route('assessments.question-bank', ['campaign' => $ibp->id]))->assertForbidden();
        $this->actingAs($hiEmployee)->get(route('coaching.manage.index', ['campaign' => $ibp->id]))->assertForbidden();

        $otherAssessment = $this->assessment($admin, [$hi->id]);
        $pool = AssessmentRandomPool::query()->create(['assessment_id' => $assessment->id, 'questions_to_select' => 1, 'points_per_question' => 1, 'display_order' => 1]);
        $this->actingAs($admin)->put(route('assessments.random-pools.update', [$otherAssessment, $pool]), ['questions_to_select' => 1, 'points_per_question' => 1])->assertNotFound();
    }

    public function test_pool_endpoint_counts_only_questions_compatible_with_assessment_scope(): void
    {
        [$admin, $ibp, $hi] = $this->people();
        $assessment = $this->assessment($admin, [$ibp->id]);
        $this->bankQuestion($admin, 'HI-only candidate', $hi);

        $this->actingAs($admin)
            ->from(route('assessments.builder', $assessment))
            ->post(route('assessments.random-pools.store', $assessment), [
                'difficulty' => 'medium',
                'questions_to_select' => 1,
                'points_per_question' => 1,
            ])
            ->assertRedirect(route('assessments.builder', $assessment))
            ->assertSessionHasErrors('questions_to_select');

        $this->assertDatabaseCount('assessment_random_pools', 0);
    }

    private function people(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ibp = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $hi = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $ibpEmployee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $hiEmployee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $ibpTeam = Team::query()->create(['name' => 'IBP Team', 'campaign_id' => $ibp->id]);
        $hiTeam = Team::query()->create(['name' => 'HI Team', 'campaign_id' => $hi->id]);
        TeamMember::query()->create(['team_id' => $ibpTeam->id, 'user_id' => $ibpEmployee->id]);
        TeamMember::query()->create(['team_id' => $hiTeam->id, 'user_id' => $hiEmployee->id]);

        return [$admin, $ibp, $hi, $ibpEmployee, $hiEmployee];
    }

    private function assessment(User $admin, array $campaignIds): Assessment
    {
        $assessment = Assessment::query()->create(['title' => 'Campaign QA '.uniqid(), 'passing_score' => 75, 'maximum_attempts' => 2, 'status' => 'published', 'applies_to_all_campaigns' => false, 'created_by' => $admin->id]);
        $assessment->campaigns()->attach($campaignIds);

        return $assessment;
    }

    private function assignment(Assessment $assessment, User $employee, User $admin, Campaign $campaign): AssessmentAssignment
    {
        return AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);
    }

    private function material(User $admin, string $title, ?Campaign $campaign = null): TrainingLibraryMaterial
    {
        $material = TrainingLibraryMaterial::query()->create(['title' => $title, 'type' => 'written', 'content' => $title, 'status' => 'active', 'created_by' => $admin->id, 'applies_to_all_campaigns' => $campaign === null]);
        if ($campaign) {
            $material->campaigns()->attach($campaign);
        }

        return $material;
    }

    private function bankQuestion(User $admin, string $text, ?Campaign $campaign = null): AssessmentBankQuestion
    {
        $question = AssessmentBankQuestion::query()->create(['question_text' => $text, 'question_type' => 'multiple_choice', 'difficulty' => 'medium', 'points' => 1, 'status' => 'active', 'created_by' => $admin->id, 'applies_to_all_campaigns' => $campaign === null]);
        $question->options()->createMany([['option_text' => 'Correct', 'is_correct' => true, 'display_order' => 1], ['option_text' => 'Incorrect', 'is_correct' => false, 'display_order' => 2]]);
        if ($campaign) {
            $question->campaigns()->attach($campaign);
        }

        return $question;
    }
}
