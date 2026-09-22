<?php

namespace Tests\Feature;

use App\Models\CallEvaluation;
use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QaDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summaries_filters_latest_average_history_and_export(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        [$employee, $campaign, $team] = $this->employeeContext('Alpha', 'A Team');
        [$other, $otherCampaign, $otherTeam] = $this->employeeContext('Beta', 'B Team');
        $this->evaluation($employee, $manager, $campaign, $team, 80, 'passed', now()->subDay());
        $latest = $this->evaluation($employee, $manager, $campaign, $team, 60, 'failed', now());
        $this->evaluation($other, $manager, $otherCampaign, $otherTeam, 100, 'passed', now());
        CoachingRecord::query()->create(['employee_id' => $employee->id, 'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'team_id' => $team->id, 'team_name' => $team->name, 'coach_id' => $manager->id, 'call_evaluation_id' => $latest->id, 'coaching_date' => today(), 'type' => 'General Coaching', 'status' => 'open', 'summary' => 'Improve discovery']);

        $this->actingAs($manager)->get(route('quality.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('quality/dashboard')->where('summary.calls_evaluated', 3)->where('summary.average_score', 80)->where('summary.passed', 2)->where('summary.failed', 1)->where('summary.open_coaching', 1)->has('performance.data', 2));
        $this->actingAs($manager)->get(route('quality.dashboard', ['campaign' => $campaign->id, 'team' => $team->id, 'employee' => $employee->id, 'range' => 'month']))->assertInertia(fn (Assert $page) => $page
            ->has('performance.data', 1)->where('performance.data.0.average_score', 70)->where('performance.data.0.latest_score', 60)->where('performance.data.0.coaching_status', 'Open'));
        $this->actingAs($manager)->get(route('quality.dashboard.employee', ['employee' => $employee, 'campaign' => $campaign->id, 'team' => $team->id]))->assertInertia(fn (Assert $page) => $page
            ->component('quality/employee-history')->where('summary.total', 2)->where('history.data.0.id', $latest->id)->where('history.data.0.coaching_record.status', 'open'));
        $this->actingAs($manager)->get(route('quality.dashboard.export', ['campaign' => $campaign->id]))->assertOk()->assertDownload('qa-performance.csv');
    }

    public function test_employee_denied_and_team_leader_is_strictly_team_scoped(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $leader = User::factory()->create(['role' => 'team_leader']);
        [$employee, $campaign, $team] = $this->employeeContext('Allowed', 'Allowed Team');
        [$other, $otherCampaign, $otherTeam] = $this->employeeContext('Denied', 'Denied Team');
        TeamLeaderAssignment::query()->create(['user_id' => $leader->id, 'team_id' => $team->id]);
        $allowed = $this->evaluation($employee, $manager, $campaign, $team, 90, 'passed', now());
        $denied = $this->evaluation($other, $manager, $otherCampaign, $otherTeam, 50, 'failed_critical', now(), true);
        $coaching = CoachingRecord::query()->create(['employee_id' => $other->id, 'campaign_id' => $otherCampaign->id, 'campaign_name' => $otherCampaign->name, 'team_id' => $otherTeam->id, 'team_name' => $otherTeam->name, 'coach_id' => $manager->id, 'call_evaluation_id' => $denied->id, 'coaching_date' => today(), 'type' => 'General Coaching', 'status' => 'open', 'summary' => 'Restricted']);

        $this->actingAs($employee)->get(route('quality.dashboard'))->assertForbidden();
        $this->actingAs($leader)->get(route('quality.dashboard'))->assertInertia(fn (Assert $page) => $page->where('summary.calls_evaluated', 1)->where('performance.data.0.employee_id', $employee->id));
        $this->actingAs($leader)->get(route('quality.evaluations.show', $allowed))->assertOk();
        $this->actingAs($leader)->get(route('quality.evaluations.show', $denied))->assertForbidden();
        $this->actingAs($leader)->get(route('coaching.manage.show', $coaching))->assertForbidden();
        $this->actingAs($leader)->get(route('quality.dashboard.employee', $other))->assertNotFound();
    }

    private function employeeContext(string $campaignName, string $teamName): array
    {
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => $campaignName, 'abbreviation' => strtoupper(substr($campaignName, 0, 3))]);
        $team = Team::query()->create(['name' => $teamName, 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['user_id' => $employee->id, 'team_id' => $team->id]);

        return [$employee, $campaign, $team];
    }

    private function evaluation(User $employee, User $manager, Campaign $campaign, Team $team, float $score, string $result, $date, bool $critical = false): CallEvaluation
    {
        return CallEvaluation::query()->create(['employee_id' => $employee->id, 'evaluator_id' => $manager->id, 'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'team_id' => $team->id, 'team_name' => $team->name, 'scorecard_name' => 'QA Card', 'scorecard_snapshot' => ['categories' => []], 'call_at' => $date, 'call_direction' => 'inbound', 'status' => 'submitted', 'points_earned' => $score, 'points_possible' => 100, 'percentage' => $score, 'result' => $result, 'has_critical_failure' => $critical, 'finalized_at' => $date, 'finalized_by' => $manager->id]);
    }
}
