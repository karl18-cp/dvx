<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentQuestion;
use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\AssessmentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSnapshotTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_preserves_hi_snapshots_and_new_records_use_ibp(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $hi = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $ibp = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $hiTeam = Team::query()->create(['name' => 'Home Improvement QA Team', 'campaign_id' => $hi->id]);
        $ibpTeam = Team::query()->create(['name' => 'IBP QA Team', 'campaign_id' => $ibp->id]);
        TeamMember::query()->create(['team_id' => $hiTeam->id, 'user_id' => $employee->id]);

        [$hiAssignment, $hiAttempt, $hiCoaching] = $this->createCampaignRecords($admin, $employee, 'HI Historical');

        $this->actingAs($admin)->post(route('team-assigning.transfer'), ['agent_id' => $employee->id, 'team_id' => $ibpTeam->id])->assertRedirect();
        $this->assertSame($ibpTeam->id, $employee->fresh()->teamMembership->team_id);
        $this->assertSame($ibp->id, $employee->teamMembership->team->campaign_id);

        [$ibpAssignment, $ibpAttempt, $ibpCoaching] = $this->createCampaignRecords($admin, $employee, 'IBP New');

        $this->assertSnapshot($hiAssignment->fresh(), $hi);
        $this->assertSnapshot($hiAttempt->fresh(), $hi);
        $this->assertSnapshot($hiCoaching->fresh(), $hi);
        $this->assertSnapshot($ibpAssignment, $ibp);
        $this->assertSnapshot($ibpAttempt, $ibp);
        $this->assertSnapshot($ibpCoaching, $ibp);
    }

    public function test_new_coaching_fails_safely_without_campaign_team_context(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($admin)->post(route('coaching.manage.store'), $this->coachingData($employee))->assertStatus(422);
        $this->assertDatabaseCount('coaching_records', 0);
    }

    private function createCampaignRecords(User $admin, User $employee, string $title): array
    {
        $assessment = Assessment::query()->create(['title' => $title, 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        AssessmentQuestion::query()->create(['assessment_id' => $assessment->id, 'question_text' => 'Campaign snapshot verification', 'question_type' => 'short_answer', 'points' => 1, 'display_order' => 1, 'is_required' => true]);
        $this->actingAs($admin)->post(route('assessments.assignments.store'), ['assessment_id' => $assessment->id, 'employee_ids' => [$employee->id], 'team_ids' => [], 'all_employees' => false])->assertRedirect();
        $assignment = AssessmentAssignment::query()->where('assessment_id', $assessment->id)->where('employee_id', $employee->id)->firstOrFail();
        $attempt = app(AssessmentAttemptService::class)->start($assignment, $employee);
        $this->actingAs($admin)->post(route('coaching.manage.store'), $this->coachingData($employee, $assessment))->assertRedirect();
        $coaching = CoachingRecord::query()->where('employee_id', $employee->id)->latest('id')->firstOrFail();

        return [$assignment, $attempt, $coaching];
    }

    private function coachingData(User $employee, ?Assessment $assessment = null): array
    {
        return ['employee_id' => $employee->id, 'assessment_id' => $assessment?->id, 'type' => 'General Coaching', 'coaching_date' => now()->toDateString(), 'summary' => 'Campaign snapshot QA'];
    }

    private function assertSnapshot(object $record, Campaign $campaign): void
    {
        $this->assertSame($campaign->id, $record->campaign_id);
        $this->assertSame($campaign->name, $record->campaign_name);
    }
}
