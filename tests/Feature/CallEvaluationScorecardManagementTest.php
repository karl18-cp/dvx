<?php

namespace Tests\Feature;

use App\Models\AssessmentSkill;
use App\Models\CallEvaluation;
use App\Models\CallEvaluationScorecard;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CallEvaluationScorecardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_is_restricted_and_index_filters_are_server_side(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $manager = User::factory()->create(['role' => 'manager']);
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP', 'is_active' => true]);
        $matching = $this->scorecard($manager, 'IBP Quality', false);
        $matching->campaigns()->attach($campaign);
        $this->scorecard($manager, 'General Communication', true);
        $this->scorecard($manager, 'Archived Other', false, 'archived');

        $this->actingAs($agent)->get(route('quality.scorecards.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('quality.scorecards.index', ['search' => 'quality', 'campaign' => $campaign->id, 'status' => 'draft']))
            ->assertInertia(fn (Assert $page) => $page->component('quality/scorecards/index')->has('scorecards.data', 1)->where('scorecards.data.0.id', $matching->id));
    }

    public function test_create_update_campaign_validation_and_activity_logging(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $campaign = Campaign::query()->create(['name' => 'Future Campaign', 'abbreviation' => 'FC', 'is_active' => true]);
        $this->actingAs($manager)->post(route('quality.scorecards.store'), ['name' => 'Future QA', 'description' => 'Compact scorecard', 'passing_score' => 80, 'applies_to_all_campaigns' => false, 'campaign_ids' => [$campaign->id]])->assertRedirect();
        $scorecard = CallEvaluationScorecard::query()->firstOrFail();
        $this->assertSame('draft', $scorecard->status);
        $this->assertSame([$campaign->id], $scorecard->campaigns()->pluck('campaigns.id')->all());
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'QA Scorecard Created', 'target_id' => $scorecard->id]);

        $this->actingAs($manager)->put(route('quality.scorecards.update', $scorecard), ['name' => 'All Campaign QA', 'description' => null, 'passing_score' => 75, 'applies_to_all_campaigns' => true, 'campaign_ids' => []])->assertRedirect();
        $this->assertTrue($scorecard->fresh()->applies_to_all_campaigns);
        $this->assertDatabaseCount('call_evaluation_scorecard_campaign', 0);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'QA Scorecard Updated', 'target_id' => $scorecard->id]);
        $this->actingAs($manager)->put(route('quality.scorecards.update', $scorecard), ['name' => 'Invalid', 'passing_score' => 101, 'applies_to_all_campaigns' => false, 'campaign_ids' => []])->assertSessionHasErrors(['passing_score', 'campaign_ids']);
    }

    public function test_category_and_criterion_crud_order_metadata_and_parent_ownership(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $skill = AssessmentSkill::query()->create(['name' => 'Objection Handling', 'is_active' => true]);
        $scorecard = $this->scorecard($manager, 'Builder');
        $other = $this->scorecard($manager, 'Other');
        $this->actingAs($manager)->post(route('quality.scorecards.categories.store', $scorecard), ['name' => 'Opening'])->assertRedirect();
        $this->actingAs($manager)->post(route('quality.scorecards.categories.store', $scorecard), ['name' => 'Closing'])->assertRedirect();
        [$opening, $closing] = $scorecard->categories()->get()->all();
        $this->actingAs($manager)->patch(route('quality.scorecards.categories.move', [$scorecard, $closing]), ['direction' => 'up'])->assertRedirect();
        $this->assertSame(['Closing', 'Opening'], $scorecard->categories()->pluck('name')->all());
        $payload = ['label' => 'Handled objection', 'guidance' => 'Use approved language.', 'points_possible' => 10, 'skill_id' => $skill->id, 'is_required' => true, 'is_critical' => true, 'allows_na' => false];
        $this->actingAs($manager)->post(route('quality.scorecards.criteria.store', [$scorecard, $opening]), $payload)->assertRedirect();
        $criterion = $opening->criteria()->firstOrFail();
        $this->assertTrue($criterion->is_required);
        $this->assertTrue($criterion->is_critical);
        $this->assertFalse($criterion->allows_na);
        $this->assertSame($skill->id, $criterion->skill_id);
        $this->actingAs($manager)->post(route('quality.scorecards.criteria.store', [$scorecard, $opening]), [...$payload, 'label' => 'Second', 'is_critical' => false, 'allows_na' => true])->assertRedirect();
        $second = $opening->criteria()->where('label', 'Second')->firstOrFail();
        $this->actingAs($manager)->patch(route('quality.scorecards.criteria.move', [$scorecard, $opening, $second]), ['direction' => 'up'])->assertRedirect();
        $this->assertSame(['Second', 'Handled objection'], $opening->criteria()->pluck('label')->all());

        $foreignCategory = $other->categories()->create(['name' => 'Foreign', 'display_order' => 1]);
        $this->actingAs($manager)->put(route('quality.scorecards.categories.update', [$scorecard, $foreignCategory]), ['name' => 'Manipulated'])->assertNotFound();
        $this->actingAs($manager)->delete(route('quality.scorecards.criteria.destroy', [$scorecard, $foreignCategory, $criterion]))->assertNotFound();
        $this->actingAs($manager)->post(route('quality.scorecards.criteria.store', [$scorecard, $opening]), [...$payload, 'points_possible' => 0])->assertSessionHasErrors('points_possible');
    }

    public function test_activation_validation_lifecycle_duplication_and_safe_deletion(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI', 'is_active' => true]);
        $scorecard = $this->scorecard($manager, 'Home QA');
        $scorecard->campaigns()->attach($campaign);
        $this->actingAs($manager)->post(route('quality.scorecards.activate', $scorecard))->assertSessionHasErrors('activation');
        $this->assertSame('draft', $scorecard->fresh()->status);
        $category = $scorecard->categories()->create(['name' => 'Opening', 'display_order' => 1]);
        $category->criteria()->create(['label' => 'Professional opening', 'guidance' => 'Approved opening.', 'points_possible' => 10, 'is_required' => true, 'is_critical' => false, 'allows_na' => true, 'display_order' => 1]);
        $this->actingAs($manager)->post(route('quality.scorecards.activate', $scorecard))->assertRedirect();
        $this->assertSame('active', $scorecard->fresh()->status);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'QA Scorecard Activated', 'target_id' => $scorecard->id]);
        $this->actingAs($manager)->post(route('quality.scorecards.duplicate', $scorecard))->assertRedirect();
        $clone = CallEvaluationScorecard::query()->where('name', 'Copy of Home QA')->firstOrFail();
        $this->assertSame('draft', $clone->status);
        $this->assertSame(1, $clone->categories()->count());
        $this->assertSame(1, $clone->criteria()->count());
        $this->assertSame([$campaign->id], $clone->campaigns()->pluck('campaigns.id')->all());
        $this->assertDatabaseCount('call_evaluations', 0);
        $this->actingAs($manager)->delete(route('quality.scorecards.destroy', $clone))->assertRedirect(route('quality.scorecards.index'));
        $this->assertModelMissing($clone);
        $this->actingAs($manager)->post(route('quality.scorecards.archive', $scorecard))->assertRedirect();
        $this->assertSame('archived', $scorecard->fresh()->status);
        $this->actingAs($manager)->put(route('quality.scorecards.update', $scorecard), ['name' => 'No', 'passing_score' => 80, 'applies_to_all_campaigns' => true, 'campaign_ids' => []])->assertUnprocessable();
        $this->actingAs($manager)->delete(route('quality.scorecards.destroy', $scorecard))->assertUnprocessable();
    }

    public function test_used_draft_cannot_be_deleted_and_live_edit_does_not_change_snapshot(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $employee = User::factory()->create(['role' => 'agent']);
        $scorecard = $this->scorecard($manager, 'Snapshot QA');
        $category = $scorecard->categories()->create(['name' => 'Opening', 'display_order' => 1]);
        $criterion = $category->criteria()->create(['label' => 'Version A', 'points_possible' => 5, 'display_order' => 1]);
        $evaluation = CallEvaluation::query()->create(['employee_id' => $employee->id, 'evaluator_id' => $manager->id, 'scorecard_id' => $scorecard->id, 'campaign_name' => 'Historical', 'team_name' => 'Historical Team', 'scorecard_name' => $scorecard->name, 'scorecard_snapshot' => ['categories' => [['criteria' => [['label' => 'Version A', 'points_possible' => 5]]]]], 'call_at' => now(), 'call_direction' => 'inbound', 'status' => 'draft']);
        $criterion->update(['label' => 'Version B', 'points_possible' => 10]);
        $this->assertSame('Version A', $evaluation->fresh()->scorecard_snapshot['categories'][0]['criteria'][0]['label']);
        $this->actingAs($manager)->delete(route('quality.scorecards.destroy', $scorecard))->assertUnprocessable();
        $this->assertModelExists($scorecard);
    }

    private function scorecard(User $creator, string $name, bool $all = false, string $status = 'draft'): CallEvaluationScorecard
    {
        return CallEvaluationScorecard::query()->create(['name' => $name, 'applies_to_all_campaigns' => $all, 'status' => $status, 'passing_score' => 80, 'created_by' => $creator->id, 'updated_by' => $creator->id]);
    }
}
