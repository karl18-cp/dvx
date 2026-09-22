<?php

namespace Tests\Feature;

use App\Models\CallEvaluation;
use App\Models\CallEvaluationScorecard;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\EvaluationCoachingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CallEvaluationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_new_evaluation_page_without_dynamic_route_collision(): void
    {
        [$manager] = $this->context();

        $this->actingAs($manager)
            ->get(route('quality.evaluations.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quality/evaluations/create')
                ->has('employees')
                ->has('scorecards'));
    }

    public function test_manager_creates_campaign_scoped_snapshot_and_employee_cannot_access_management(): void
    {
        [$manager, $employee, $campaign, $scorecard] = $this->context();
        $this->actingAs($employee)->get(route('quality.evaluations.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('quality.evaluations.store'), ['employee_id' => $employee->id, 'scorecard_id' => $scorecard->id, 'call_at' => now()->toDateTimeString(), 'call_direction' => 'inbound', 'call_reference' => 'QA-100'])->assertRedirect();
        $evaluation = CallEvaluation::query()->firstOrFail();
        $this->assertSame($campaign->id, $evaluation->campaign_id);
        $this->assertSame('Test Team', $evaluation->team_name);
        $this->assertSame('Greeting', $evaluation->scorecard_snapshot['categories'][0]['criteria'][0]['label']);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Call Evaluation Created', 'target_id' => $evaluation->id]);
    }

    public function test_incompatible_draft_and_archived_scorecards_are_rejected_server_side(): void
    {
        [$manager, $employee] = $this->context();
        $foreign = Campaign::query()->create(['name' => 'Foreign', 'abbreviation' => 'FOR']);
        foreach (['active', 'draft', 'archived'] as $status) {
            $card = $this->scorecard($manager, $foreign, $status);
            $response = $this->actingAs($manager)->post(route('quality.evaluations.store'), ['employee_id' => $employee->id, 'scorecard_id' => $card->id, 'call_at' => now(), 'call_direction' => 'inbound']);
            $response->assertSessionHasErrors('scorecard_id');
        }
        $this->assertDatabaseCount('call_evaluations', 0);
    }

    public function test_draft_scores_validate_snapshot_ownership_ranges_and_na_rules(): void
    {
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $url = route('quality.evaluations.update', $evaluation);
        $this->actingAs($manager)->put($url, ['criteria' => [['key' => 'manipulated', 'points_awarded' => 1, 'is_na' => false]]])->assertSessionHasErrors('criteria');
        $this->actingAs($manager)->put($url, ['criteria' => [['key' => $this->key($evaluation, 0), 'points_awarded' => 11, 'is_na' => false]]])->assertSessionHasErrors('criteria');
        $this->actingAs($manager)->put($url, ['criteria' => [['key' => $this->key($evaluation, 0), 'points_awarded' => null, 'is_na' => true]]])->assertSessionHasErrors('criteria');
        $this->assertDatabaseCount('call_evaluation_criterion_results', 0);
    }

    public function test_submission_calculates_na_normalization_and_is_idempotent_and_immutable(): void
    {
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $criteria = [
            ['key' => $this->key($evaluation, 0), 'points_awarded' => 8, 'is_na' => false, 'comment' => 'Good opening'],
            ['key' => $this->key($evaluation, 1), 'points_awarded' => null, 'is_na' => true, 'comment' => null],
        ];
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['criteria' => $criteria, 'strengths' => 'Professional tone'])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('quality.evaluations.submit', $evaluation))->assertRedirect(route('quality.evaluations.index'));
        $evaluation->refresh();
        $this->assertSame('submitted', $evaluation->status);
        $this->assertEquals(8, $evaluation->points_earned);
        $this->assertEquals(10, $evaluation->points_possible);
        $this->assertEquals(80, $evaluation->percentage);
        $this->assertSame('passed', $evaluation->result);
        $submittedAt = $evaluation->finalized_at;
        $this->actingAs($manager)->post(route('quality.evaluations.submit', $evaluation))->assertRedirect();
        $this->assertEquals($submittedAt, $evaluation->fresh()->finalized_at);
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['strengths' => 'Changed'])->assertConflict();
        $this->actingAs($manager)->delete(route('quality.evaluations.destroy', $evaluation))->assertConflict();
    }

    public function test_zero_on_critical_criterion_preserves_numerical_score_and_fails_critical(): void
    {
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['criteria' => [
            ['key' => $this->key($evaluation, 0), 'points_awarded' => 0, 'is_na' => false],
            ['key' => $this->key($evaluation, 1), 'points_awarded' => 5, 'is_na' => false],
        ]]);
        $this->actingAs($manager)->post(route('quality.evaluations.submit', $evaluation))->assertRedirect();
        $evaluation->refresh();
        $this->assertEquals(33.33, $evaluation->percentage);
        $this->assertTrue($evaluation->has_critical_failure);
        $this->assertSame('failed_critical', $evaluation->result);
    }

    public function test_recording_is_private_replaceable_in_draft_and_deleted_with_draft(): void
    {
        Storage::fake('local');
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $first = UploadedFile::fake()->create('call.mp3', 100, 'audio/mpeg');
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['recording' => $first])->assertSessionHasNoErrors();
        $evaluation->refresh();
        $old = $evaluation->storage_key;
        Storage::disk('local')->assertExists($old);
        $this->actingAs($employee)->get(route('quality.evaluations.recording', $evaluation))->assertForbidden();
        $second = UploadedFile::fake()->create('replacement.wav', 100, 'audio/wav');
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['recording' => $second])->assertSessionHasNoErrors();
        $evaluation->refresh();
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($evaluation->storage_key);
        $key = $evaluation->storage_key;
        $this->actingAs($manager)->delete(route('quality.evaluations.destroy', $evaluation))->assertRedirect();
        Storage::disk('local')->assertMissing($key);
    }

    public function test_docx_is_stored_as_a_private_replaceable_supporting_attachment(): void
    {
        Storage::fake('local');
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $first = UploadedFile::fake()->create('original-evaluation.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['evaluation_document' => $first])->assertSessionHasNoErrors();
        $evaluation->refresh();
        $old = $evaluation->document_storage_key;
        $this->assertSame('original-evaluation.docx', $evaluation->document_original_filename);
        Storage::disk('local')->assertExists($old);
        $this->actingAs($employee)->get(route('quality.evaluations.document', $evaluation))->assertForbidden();
        $this->actingAs($manager)->get(route('quality.evaluations.document', $evaluation))->assertDownload('original-evaluation.docx');

        $replacement = UploadedFile::fake()->create('replacement-evaluation.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['evaluation_document' => $replacement])->assertSessionHasNoErrors();
        $evaluation->refresh();
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($evaluation->document_storage_key);
        $key = $evaluation->document_storage_key;

        $this->actingAs($manager)->delete(route('quality.evaluations.destroy', $evaluation))->assertRedirect();
        Storage::disk('local')->assertMissing($key);
    }

    public function test_submitted_evaluation_can_prefill_and_create_explicit_linked_coaching(): void
    {
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['criteria' => [
            ['key' => $this->key($evaluation, 0), 'points_awarded' => 8, 'is_na' => false],
            ['key' => $this->key($evaluation, 1), 'points_awarded' => null, 'is_na' => true],
        ], 'areas_for_improvement' => 'Improve discovery.', 'overall_feedback' => 'Good call.']);
        $this->actingAs($manager)->post(route('quality.evaluations.submit', $evaluation));

        $this->actingAs($manager)->get(route('coaching.manage.index', ['create' => 1, 'call_evaluation' => $evaluation->id]))
            ->assertInertia(fn ($page) => $page->where('prefill.employee', (string) $employee->id)->where('prefill.call_evaluation_id', (string) $evaluation->id)->where('prefill.areas_for_improvement', 'Improve discovery.'));
        $this->actingAs($manager)->post(route('coaching.manage.store'), ['employee_id' => $employee->id, 'call_evaluation_id' => $evaluation->id, 'type' => 'General Coaching', 'coaching_date' => now()->toDateString(), 'summary' => 'QA follow-up', 'areas_for_improvement' => 'Improve discovery.'])->assertRedirect();
        $this->assertDatabaseHas('coaching_records', ['employee_id' => $employee->id, 'call_evaluation_id' => $evaluation->id]);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Coaching Created from Evaluation']);
    }

    public function test_phase_three_draft_resume_required_blocking_playback_and_profile_visibility(): void
    {
        Storage::fake('local');
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $draftHiddenFromProfile = $this->createEvaluation($manager, $employee, $scorecard);

        $this->actingAs($manager)->post(route('quality.evaluations.submit', $evaluation))
            ->assertSessionHasErrors('submission');
        $this->assertSame('draft', $evaluation->fresh()->status);

        $criteria = [
            ['key' => $this->key($evaluation, 0), 'points_awarded' => 9, 'is_na' => false, 'comment' => 'Greeting evidence at 00:12.'],
            ['key' => $this->key($evaluation, 1), 'points_awarded' => null, 'is_na' => true, 'comment' => 'Closing was not applicable.'],
        ];
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), [
            'criteria' => $criteria,
            'strengths' => 'Professional tone.',
            'areas_for_improvement' => 'Confirm the next step.',
            'overall_feedback' => 'Strong compliant call.',
            'recommended_action' => 'Continue call reviews.',
            'recording' => UploadedFile::fake()->create('phase-three.mp3', 100, 'audio/mpeg'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)->get(route('quality.evaluations.recording', $evaluation))->assertOk();
        $this->actingAs($manager)->get(route('quality.evaluations.edit', $evaluation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('evaluation.strengths', 'Professional tone.')
                ->where('evaluation.areas_for_improvement', 'Confirm the next step.')
                ->where('evaluation.overall_feedback', 'Strong compliant call.')
                ->where('evaluation.recommended_action', 'Continue call reviews.')
                ->has('evaluation.criterion_results', 2)
                ->where('evaluation.criterion_results.0.comment', 'Greeting evidence at 00:12.')
                ->where('evaluation.criterion_results.1.is_na', true));

        $this->actingAs($manager)->post(route('quality.evaluations.submit', $evaluation))->assertRedirect(route('quality.evaluations.index'));
        $this->actingAs($manager)->get(route('coaching.profile', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->has('qaEvaluations', 1)
                ->where('qaEvaluations.0.id', $evaluation->id));
        $this->assertSame('draft', $draftHiddenFromProfile->fresh()->status);
        $this->assertDatabaseCount('call_evaluation_criterion_results', 2);
    }

    public function test_submitted_evaluation_automatically_appears_in_coaching_and_reuses_existing_record(): void
    {
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        $this->assertDatabaseCount('coaching_records', 0);
        $this->actingAs($manager)->put(route('quality.evaluations.update', $evaluation), ['strengths' => 'Clear explanation', 'recommended_action' => 'Practise discovery', 'criteria' => [
            ['key' => $this->key($evaluation, 0), 'points_awarded' => 8, 'is_na' => false],
            ['key' => $this->key($evaluation, 1), 'points_awarded' => null, 'is_na' => true],
        ]])->assertSessionHasNoErrors();
        $this->post(route('quality.evaluations.submit', $evaluation))->assertRedirect();
        $record = $evaluation->coachingRecord()->firstOrFail();
        $this->assertSame('Clear explanation', $record->strengths);
        $this->assertSame('Practise discovery', $record->action_plan);
        $this->get(route('coaching.manage.index'))->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $record->id));
        $record->update(['summary' => 'Coach edited this', 'status' => 'completed']);
        app(EvaluationCoachingService::class)->sync($evaluation);
        $this->post(route('quality.evaluations.submit', $evaluation))->assertRedirect();
        $this->assertDatabaseCount('coaching_records', 1);
        $this->assertSame('Coach edited this', $record->fresh()->summary);
        $this->assertSame('completed', $record->fresh()->status);
        $this->actingAs($employee)->get(route('coaching.my.index'))->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
    }

    public function test_coaching_export_includes_private_attachment_links_and_report(): void
    {
        Storage::fake('local');
        [$manager, $employee, , $scorecard] = $this->context();
        $evaluation = $this->createEvaluation($manager, $employee, $scorecard);
        Storage::disk('local')->put('calls/test.mp3', 'recording-bytes');
        Storage::disk('local')->put('calls/test.docx', 'document-bytes');
        $evaluation->update(['status' => 'submitted', 'storage_disk' => 'local', 'storage_key' => 'calls/test.mp3', 'original_filename' => 'call.mp3', 'document_storage_disk' => 'local', 'document_storage_key' => 'calls/test.docx', 'document_original_filename' => 'notes.docx']);
        $record = app(EvaluationCoachingService::class)->sync($evaluation);
        $response = $this->actingAs($manager)->getJson(route('coaching.manage.export', $record))->assertOk()->assertJsonCount(2, 'attachments')->assertJsonPath('attachments.0.name', 'recording/call.mp3')->assertJsonPath('attachments.1.name', 'document/notes.docx')->assertJsonPath('warnings', []);
        $this->assertStringContainsString($employee->name, implode('\n', $response->json('lines')));
        $this->assertStringNotContainsString('calls/test.mp3', $response->getContent());
        $this->actingAs($employee)->getJson(route('coaching.manage.export', $record))->assertForbidden();
        $leader = User::factory()->create(['role' => 'team_leader']);
        $this->actingAs($leader)->getJson(route('coaching.manage.export', $record))->assertForbidden();
        Storage::disk('local')->delete('calls/test.mp3');
        $this->actingAs($manager)->getJson(route('coaching.manage.export', $record))->assertOk()->assertJsonCount(1, 'attachments')->assertJsonCount(1, 'warnings');
    }

    private function context(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $team = Team::query()->create(['name' => 'Test Team', 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $employee->id]);

        return [$manager, $employee, $campaign, $this->scorecard($manager, $campaign, 'active')];
    }

    private function scorecard(User $manager, Campaign $campaign, string $status): CallEvaluationScorecard
    {
        $card = CallEvaluationScorecard::query()->create(['name' => "{$status} QA ".uniqid(), 'status' => $status, 'passing_score' => 80, 'created_by' => $manager->id]);
        $card->campaigns()->attach($campaign);
        $category = $card->categories()->create(['name' => 'Opening']);
        $category->criteria()->create(['label' => 'Greeting', 'points_possible' => 10, 'is_required' => true, 'is_critical' => true, 'allows_na' => false]);
        $category->criteria()->create(['label' => 'Optional close', 'points_possible' => 5, 'is_required' => false, 'allows_na' => true]);

        return $card;
    }

    private function createEvaluation(User $manager, User $employee, CallEvaluationScorecard $scorecard): CallEvaluation
    {
        $this->actingAs($manager)->post(route('quality.evaluations.store'), ['employee_id' => $employee->id, 'scorecard_id' => $scorecard->id, 'call_at' => now(), 'call_direction' => 'inbound']);

        return CallEvaluation::query()->latest('id')->firstOrFail();
    }

    private function key(CallEvaluation $evaluation, int $index): string
    {
        return collect($evaluation->scorecard_snapshot['categories'])->flatMap(fn ($category) => $category['criteria'])->values()[$index]['key'];
    }
}
