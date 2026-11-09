<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\PerformanceGoal;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\PerformanceAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_review_requires_employee_acknowledgement_before_finalization(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active', 'name' => 'Jamie Agent', 'username' => 'DVX100']);
        $payload = [
            'employee_id' => $employee->id,
            'period_month' => '2026-11',
            'overall_rating' => 4.25,
            'kpis' => [[
                'name' => 'Quality score', 'target' => '90%', 'actual' => '94%',
                'weight' => 100, 'rating' => 4.5, 'comment' => 'Above target',
            ]],
            'strengths' => 'Accurate calls',
            'development_areas' => 'Shorter hold time',
            'manager_summary' => 'Strong monthly performance.',
        ];

        $this->actingAs($admin)->post('/performance/reviews', $payload)->assertSessionHasNoErrors();
        $review = PerformanceReview::firstOrFail();
        $this->actingAs($admin)->patch("/performance/reviews/{$review->id}/transition", ['action' => 'share'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch("/performance/reviews/{$review->id}/transition", ['action' => 'finalize'])->assertStatus(422);

        $this->actingAs($employee)->post("/performance/review/{$review->id}/acknowledge", [
            'typed_name' => 'Jamie Agent',
            'employee_comment' => 'I have reviewed this appraisal.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('acknowledged', $review->fresh()->status);

        $this->actingAs($admin)->patch("/performance/reviews/{$review->id}/transition", ['action' => 'finalize'])->assertSessionHasNoErrors();
        $this->assertSame('finalized', $review->fresh()->status);
        $this->assertDatabaseHas('performance_acknowledgements', ['employee_id' => $employee->id, 'record_type' => 'review', 'record_id' => $review->id]);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $employee->id, 'type' => 'performance_review']);
        $this->assertDatabaseHas('activity_logs', ['category' => 'performance', 'action' => 'Monthly KPI review finalized']);
    }

    public function test_goals_meetings_and_improvement_plans_are_recorded_and_acknowledged(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active', 'name' => 'Taylor Agent', 'username' => 'DVX101']);

        $this->actingAs($employee)->post('/performance/goals', [
            'employee_id' => $employee->id, 'title' => 'Improve QA score', 'category' => 'quality',
            'description' => 'Reach the monthly target', 'baseline_value' => 82, 'target_value' => 90,
            'unit' => '%', 'weight' => 50, 'start_date' => '2026-11-01', 'due_date' => '2026-11-30',
        ])->assertSessionHasNoErrors();
        $goal = PerformanceGoal::firstOrFail();
        $this->actingAs($employee)->patch("/performance/goals/{$goal->id}", ['progress' => 60, 'status' => 'active', 'outcome' => 'On track'])->assertSessionHasNoErrors();
        $this->assertSame(60, $goal->fresh()->progress);

        $this->actingAs($admin)->post('/performance/meetings', [
            'employee_id' => $employee->id, 'scheduled_at' => '2026-11-15 10:00:00',
            'agenda' => 'Monthly performance check-in', 'manager_notes' => null,
            'action_items' => ['Review two calls'], 'next_meeting_date' => '2026-12-15',
        ])->assertSessionHasNoErrors();
        $meeting = PerformanceMeeting::firstOrFail();
        $this->actingAs($admin)->patch("/performance/meetings/{$meeting->id}", [
            'scheduled_at' => '2026-11-15 10:00:00', 'agenda' => 'Monthly performance check-in',
            'manager_notes' => 'Discussed QA opportunities.', 'action_items' => ['Review two calls'],
            'next_meeting_date' => '2026-12-15', 'status' => 'completed',
        ])->assertSessionHasNoErrors();
        $this->actingAs($employee)->post("/performance/meeting/{$meeting->id}/acknowledge", ['typed_name' => 'Taylor Agent'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/performance/plans', [
            'employee_id' => $employee->id, 'source_review_id' => null, 'title' => 'Quality recovery plan',
            'reason' => 'Quality below required target', 'expectations' => 'Maintain 90% QA',
            'support_plan' => 'Weekly coaching', 'success_criteria' => ['90% QA for four weeks'],
            'start_date' => '2026-11-01', 'review_date' => '2026-11-15', 'end_date' => '2026-11-30',
        ])->assertSessionHasNoErrors();
        $plan = PerformanceImprovementPlan::firstOrFail();
        $this->actingAs($employee)->post("/performance/pip/{$plan->id}/acknowledge", ['typed_name' => 'Taylor Agent'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch("/performance/plans/{$plan->id}", [
            'status' => 'completed', 'progress_note' => 'Target sustained.', 'resolution_notes' => 'Plan completed.',
            'review_date' => '2026-11-15', 'end_date' => '2026-11-30',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('performance_acknowledgements', ['record_type' => 'meeting', 'record_id' => $meeting->id]);
        $this->assertDatabaseHas('performance_acknowledgements', ['record_type' => 'pip', 'record_id' => $plan->id]);
        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertCount(1, $plan->fresh()->progress_updates);
    }

    public function test_team_leader_is_limited_to_assigned_team_members(): void
    {
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active', 'username' => 'DVX200']);
        $member = User::factory()->create(['role' => 'agent', 'status' => 'active', 'username' => 'DVX201']);
        $outsider = User::factory()->create(['role' => 'agent', 'status' => 'active', 'username' => 'DVX202']);
        $campaign = Campaign::create(['name' => 'Performance Campaign', 'abbreviation' => 'PERF']);
        $team = Team::create(['name' => 'Performance Team', 'campaign_id' => $campaign->id]);
        TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $member->id]);
        $access = app(PerformanceAccessService::class);

        $this->assertTrue($access->canManageEmployee($leader, $member));
        $this->assertFalse($access->canManageEmployee($leader, $outsider));
        $this->assertEqualsCanonicalizing([$leader->id, $member->id], $access->visibleEmployees($leader)->pluck('id')->all());

        $this->actingAs($leader)->post('/performance/reviews', [
            'employee_id' => $outsider->id, 'period_month' => '2026-11', 'overall_rating' => 3,
            'kpis' => [['name' => 'QA', 'target' => '90', 'actual' => '85', 'weight' => 100, 'rating' => 3, 'comment' => null]],
            'strengths' => null, 'development_areas' => null, 'manager_summary' => 'Not authorized.',
        ])->assertForbidden();
    }
}
