<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assessment = Assessment::query()->create(['title' => 'Scheduled assessment', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        $campaign = Campaign::query()->create(['name' => 'Campaign A', 'abbreviation' => 'A']);
        $team = Team::query()->create(['name' => 'Team A', 'campaign_id' => $campaign->id]);

        return [$admin, $assessment, $campaign, $team];
    }

    private function assignment($admin, $assessment, $campaign, $team, array $extra = []): AssessmentAssignment
    {
        return AssessmentAssignment::query()->create([
            'assessment_id' => $assessment->id, 'employee_id' => User::factory()->create(['role' => 'agent'])->id,
            'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'source_team_id' => $team->id,
            'assigned_by' => $admin->id, 'assigned_at' => '2026-09-10 01:00:00', 'status' => 'assigned', ...$extra,
        ]);
    }

    private function payload($assessment, $campaign, $team): array
    {
        return ['assessment_id' => (string) $assessment->id, 'campaign_id' => (string) $campaign->id, 'team_id' => (string) $team->id, 'available_from' => '2026-09-15T09:00', 'due_date' => '2026-09-15T17:00'];
    }

    public function test_campaign_team_assessment_and_local_date_filters_combine(): void
    {
        [$admin, $assessment, $campaign, $team] = $this->context();
        $assessment->update(['due_at' => '2026-09-10 16:30:00']);
        $match = $this->assignment($admin, $assessment, $campaign, $team, ['assigned_at' => '2026-09-09 16:30:00']);
        $this->assignment($admin, $assessment, $campaign, $team, ['assigned_at' => '2026-09-09 15:59:00']);
        $other = Campaign::query()->create(['name' => 'Campaign B', 'abbreviation' => 'B']);
        $this->assignment($admin, $assessment, $other, $team, ['assigned_at' => '2026-09-09 16:30:00']);
        $this->actingAs($admin)->get(route('assessments.assignments', [
            'assessment_id' => $assessment->id, 'campaign_id' => $campaign->id, 'team_id' => $team->id,
            'assigned_from' => '2026-09-10', 'assigned_to' => '2026-09-10',
            'due_from' => '2026-09-11', 'due_to' => '2026-09-11',
        ]))->assertOk()->assertInertia(fn ($page) => $page->has('assignments.data', 1)->where('assignments.data.0.id', $match->id));
        $this->get(route('assessments.assignments', ['due_to' => '2026-09-12']))->assertOk();
        $this->get(route('assessments.assignments', ['assigned_from' => '2026-09-12', 'assigned_to' => '2026-09-10']))->assertSessionHasErrors('assigned_to');
    }

    public function test_review_and_schedule_only_update_unstarted_assignments_in_scope(): void
    {
        [$admin, $assessment, $campaign, $team] = $this->context();
        $target = $this->assignment($admin, $assessment, $campaign, $team);
        $completed = $this->assignment($admin, $assessment, $campaign, $team, ['status' => 'passed']);
        $started = $this->assignment($admin, $assessment, $campaign, $team);
        AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'assignment_id' => $started->id, 'employee_id' => $started->employee_id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now(), 'status' => 'in_progress']);
        $other = Campaign::query()->create(['name' => 'Campaign B', 'abbreviation' => 'B']);
        $outside = $this->assignment($admin, $assessment, $other, $team);
        $data = $this->payload($assessment, $campaign, $team);
        $review = $this->actingAs($admin)->getJson(route('assessments.assignments.schedule.preview', $data))->assertOk()->assertJson(['count' => 1, 'skipped' => 2])->json();
        $this->patch(route('assessments.assignments.schedule.store'), [...$data, 'review_token' => $review['review_token']])->assertRedirect();
        $this->assertSame('2026-09-15 01:00:00', $target->fresh()->available_from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15 09:00:00', $target->fresh()->due_date->format('Y-m-d H:i:s'));
        foreach ([$completed, $started, $outside] as $row) {
            $this->assertNull($row->fresh()->due_date);
        }
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Assessment Assignment Scheduled', 'target_id' => $target->id]);
    }

    public function test_schedule_requires_fresh_review_and_rejects_invalid_dates(): void
    {
        [$admin, $assessment, $campaign, $team] = $this->context();
        $target = $this->assignment($admin, $assessment, $campaign, $team);
        $data = $this->payload($assessment, $campaign, $team);
        $review = $this->actingAs($admin)->getJson(route('assessments.assignments.schedule.preview', $data))->assertOk()->json();
        $target->update(['status' => 'in_progress']);
        $this->patch(route('assessments.assignments.schedule.store'), [...$data, 'review_token' => $review['review_token']])->assertSessionHasErrors('review_token');
        $this->assertNull($target->fresh()->due_date);
        $this->getJson(route('assessments.assignments.schedule.preview', [...$data, 'due_date' => '2026-09-14T17:00']))->assertUnprocessable()->assertJsonValidationErrors('due_date');
        $this->patch(route('assessments.assignments.schedule.store'), $data)->assertSessionHasErrors('review_token');
    }

    public function test_employees_cannot_preview_or_save_schedules_and_archived_assessments_are_rejected(): void
    {
        [$admin, $assessment, $campaign, $team] = $this->context();
        $data = $this->payload($assessment, $campaign, $team);
        $employee = User::factory()->create(['role' => 'agent']);
        $this->actingAs($employee)->getJson(route('assessments.assignments.schedule.preview', $data))->assertForbidden();
        $this->patch(route('assessments.assignments.schedule.store'), $data)->assertForbidden();
        $assessment->update(['status' => 'archived']);
        $this->actingAs($admin)->getJson(route('assessments.assignments.schedule.preview', $data))->assertUnprocessable()->assertJsonValidationErrors('assessment_id');
    }
}
