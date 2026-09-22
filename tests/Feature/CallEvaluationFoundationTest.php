<?php

namespace Tests\Feature;

use App\Models\AssessmentSkill;
use App\Models\CallEvaluationScorecard;
use App\Models\CallEvaluationScorecardCategory;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\CallEvaluationSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CallEvaluationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_snapshots_scorecard_campaign_team_skill_and_order_immutably(): void
    {
        [$employee, $evaluator, $campaign, $team] = $this->employeeContext('Future Campaign', 'Future Team');
        $skill = AssessmentSkill::query()->create(['name' => 'Probing']);
        $scorecard = CallEvaluationScorecard::query()->create(['name' => 'Future QA', 'description' => 'Original', 'status' => 'active', 'passing_score' => 80, 'created_by' => $evaluator->id]);
        $scorecard->campaigns()->attach($campaign);
        $second = CallEvaluationScorecardCategory::query()->create(['scorecard_id' => $scorecard->id, 'name' => 'Closing', 'display_order' => 2]);
        $first = CallEvaluationScorecardCategory::query()->create(['scorecard_id' => $scorecard->id, 'name' => 'Opening', 'display_order' => 1]);
        $criterion = $first->criteria()->create(['skill_id' => $skill->id, 'label' => 'Professional opening', 'guidance' => 'Use the approved introduction.', 'points_possible' => 10, 'is_required' => true, 'is_critical' => true, 'allows_na' => false, 'display_order' => 1]);
        $second->criteria()->create(['label' => 'Clear close', 'points_possible' => 5, 'display_order' => 1]);

        $evaluation = app(CallEvaluationSnapshotService::class)->createEvaluation($scorecard, $employee, $evaluator, ['call_at' => now(), 'call_direction' => 'outbound', 'call_reference' => 'QA-001']);
        $snapshot = $evaluation->scorecard_snapshot;

        $this->assertSame($campaign->id, $evaluation->campaign_id);
        $this->assertSame('Future Campaign', $evaluation->campaign_name);
        $this->assertSame($team->id, $evaluation->team_id);
        $this->assertSame('Future Team', $evaluation->team_name);
        $this->assertSame(['Opening', 'Closing'], array_column($snapshot['categories'], 'name'));
        $this->assertSame('criterion-'.$criterion->id, $snapshot['categories'][0]['criteria'][0]['key']);
        $this->assertSame('Probing', $snapshot['categories'][0]['criteria'][0]['skill_name']);
        $this->assertTrue($snapshot['categories'][0]['criteria'][0]['is_critical']);
        $this->assertFalse($snapshot['categories'][0]['criteria'][0]['allows_na']);

        $scorecard->update(['name' => 'Changed QA', 'passing_score' => 95]);
        $criterion->update(['label' => 'Changed criterion', 'points_possible' => 50]);
        $employee->teamMembership()->update(['team_id' => Team::query()->create(['name' => 'Moved Team', 'campaign_id' => Campaign::query()->create(['name' => 'Moved Campaign', 'abbreviation' => 'MC'])->id])->id]);

        $evaluation->refresh();
        $this->assertSame('Future QA', $evaluation->scorecard_name);
        $this->assertEquals(80.0, $evaluation->scorecard_snapshot['passing_score']);
        $this->assertSame('Professional opening', $evaluation->scorecard_snapshot['categories'][0]['criteria'][0]['label']);
        $this->assertEquals(10.0, $evaluation->scorecard_snapshot['categories'][0]['criteria'][0]['points_possible']);
        $this->assertSame('Future Campaign', $evaluation->campaign_name);
        $this->assertSame('Future Team', $evaluation->team_name);
    }

    public function test_scorecard_scope_and_activation_are_authoritative(): void
    {
        [$employee, $evaluator] = $this->employeeContext('Home Improvement', 'Upfinity');
        $ibp = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $scorecard = CallEvaluationScorecard::query()->create(['name' => 'IBP QA', 'status' => 'active', 'passing_score' => 75, 'created_by' => $evaluator->id]);
        $scorecard->campaigns()->attach($ibp);
        $category = $scorecard->categories()->create(['name' => 'Opening', 'display_order' => 1]);
        $category->criteria()->create(['label' => 'Greeting', 'points_possible' => 5, 'display_order' => 1]);

        $this->expectException(ValidationException::class);
        app(CallEvaluationSnapshotService::class)->createEvaluation($scorecard, $employee, $evaluator, ['call_at' => now(), 'call_direction' => 'inbound']);
    }

    public function test_all_campaign_scorecard_supports_future_campaign_without_code_changes(): void
    {
        [$employee, $evaluator] = $this->employeeContext('New Campaign', 'New Team');
        $scorecard = CallEvaluationScorecard::query()->create(['name' => 'General QA', 'applies_to_all_campaigns' => true, 'status' => 'active', 'passing_score' => 70, 'created_by' => $evaluator->id]);
        $category = $scorecard->categories()->create(['name' => 'Communication']);
        $category->criteria()->create(['label' => 'Clear speech', 'points_possible' => 5]);

        $evaluation = app(CallEvaluationSnapshotService::class)->createEvaluation($scorecard, $employee, $evaluator, ['call_at' => now(), 'call_direction' => 'inbound']);

        $this->assertSame('New Campaign', $evaluation->campaign_name);
        $this->assertTrue($evaluation->scorecard_snapshot['applies_to_all_campaigns']);
        $this->assertDatabaseCount('call_evaluations', 1);
    }

    private function employeeContext(string $campaignName, string $teamName): array
    {
        $campaign = Campaign::query()->create(['name' => $campaignName, 'abbreviation' => strtoupper(substr($campaignName, 0, 3))]);
        $team = Team::query()->create(['name' => $teamName, 'campaign_id' => $campaign->id]);
        $employee = User::factory()->create(['role' => 'agent']);
        $evaluator = User::factory()->create(['role' => 'manager']);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $employee->id]);

        return [$employee, $evaluator, $campaign, $team];
    }
}
