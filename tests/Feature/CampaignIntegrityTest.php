<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\CampaignIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CampaignIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrity_audit_distinguishes_legacy_unknowns_from_campaign_era_defects(): void
    {
        config(['campaigns.snapshot_cutoff' => '2026-08-14 06:26:17']);
        Carbon::setTestNow('2026-08-13 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $team = Team::query()->create(['name' => 'IBP Team', 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $employee->id]);
        $assessment = Assessment::query()->create(['title' => 'Integrity', 'passing_score' => 75, 'maximum_attempts' => 2, 'status' => 'published', 'created_by' => $admin->id]);
        AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);

        Carbon::setTestNow('2026-08-15 12:00:00');
        $campaignEraAssessment = Assessment::query()->create(['title' => 'Campaign Era Integrity', 'passing_score' => 75, 'maximum_attempts' => 2, 'status' => 'published', 'created_by' => $admin->id]);
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $campaignEraAssessment->id, 'employee_id' => $employee->id, 'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);
        AssessmentAttempt::query()->create(['assessment_id' => $campaignEraAssessment->id, 'assignment_id' => $assignment->id, 'employee_id' => $employee->id, 'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now(), 'status' => 'in_progress']);

        $result = app(CampaignIntegrityService::class)->inspect();
        $this->assertSame(1, $result['legacy_unknown_assessment_assignments']);
        $this->assertSame(0, $result['campaign_era_invalid_assessment_assignments']);
        $this->assertSame(0, $result['campaign_era_invalid_assessment_attempts']);
        $this->assertSame(0, $result['campaign_era_assignment_attempt_mismatches']);
    }
}
