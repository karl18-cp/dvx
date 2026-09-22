<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentTrainingMaterial;
use App\Models\AssessmentTrainingProgress;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_team_and_all_assignment_are_deduplicated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $one = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $two = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => 'Test', 'abbreviation' => 'T']);
        $team = Team::query()->create(['name' => 'Alpha', 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $one->id]);
        $assessment = Assessment::query()->create(['title' => 'Published', 'difficulty' => 'beginner', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        $payload = ['assessment_id' => $assessment->id, 'employee_ids' => [$one->id, $two->id], 'team_ids' => [$team->id], 'all_employees' => true, 'available_from' => '2026-08-12 09:00:00', 'due_date' => '2026-08-20 09:00:00'];
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('assessment_assignments', 2);
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('assessment_assignments', 2);
    }

    public function test_employee_can_receive_different_assessments_on_the_same_day_but_not_the_same_assessment_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $first = Assessment::query()->create(['title' => 'Grammar', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        $second = Assessment::query()->create(['title' => 'Product Knowledge', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        $payload = fn (Assessment $assessment) => ['assessment_id' => $assessment->id, 'employee_ids' => [$employee->id], 'team_ids' => [], 'all_employees' => false, 'available_from' => '2026-08-18 09:00:00', 'due_date' => '2026-08-18 17:00:00'];

        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload($first))->assertRedirect();
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload($second))->assertRedirect();
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload($first))->assertRedirect();

        $this->assertDatabaseCount('assessment_assignments', 2);
        $this->assertDatabaseHas('assessment_assignments', ['assessment_id' => $first->id, 'employee_id' => $employee->id]);
        $this->assertDatabaseHas('assessment_assignments', ['assessment_id' => $second->id, 'employee_id' => $employee->id]);
    }

    public function test_draft_cannot_be_assigned_and_employee_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $assessment = Assessment::query()->create(['title' => 'Draft', 'difficulty' => 'beginner', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'draft', 'created_by' => $admin->id]);
        $payload = ['assessment_id' => $assessment->id, 'employee_ids' => [$employee->id], 'team_ids' => [], 'all_employees' => false];
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload)->assertSessionHasErrors('assessment_id');
        $this->actingAs($employee)->post(route('assessments.assignments.store'), $payload)->assertForbidden();
    }

    public function test_employee_outside_assessment_campaign_scope_is_rejected_by_the_backend(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ibp = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $homeImprovement = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $homeImprovementTeam = Team::query()->create(['name' => 'HI QA Team', 'campaign_id' => $homeImprovement->id]);
        $homeImprovementEmployee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        TeamMember::query()->create(['team_id' => $homeImprovementTeam->id, 'user_id' => $homeImprovementEmployee->id]);
        $assessment = Assessment::query()->create([
            'title' => 'IBP Only QA Assessment',
            'passing_score' => 75,
            'maximum_attempts' => 1,
            'status' => 'published',
            'applies_to_all_campaigns' => false,
            'created_by' => $admin->id,
        ]);
        $assessment->campaigns()->attach($ibp->id);

        $this->actingAs($admin)->post(route('assessments.assignments.store'), [
            'assessment_id' => $assessment->id,
            'employee_ids' => [$homeImprovementEmployee->id],
            'team_ids' => [],
            'all_employees' => false,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('assessment_assignments', [
            'assessment_id' => $assessment->id,
            'employee_id' => $homeImprovementEmployee->id,
        ]);
    }

    public function test_reverse_campaign_assignment_is_rejected_by_the_backend(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ibp = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $homeImprovement = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $ibpTeam = Team::query()->create(['name' => 'IBP QA Team', 'campaign_id' => $ibp->id]);
        $ibpEmployee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        TeamMember::query()->create(['team_id' => $ibpTeam->id, 'user_id' => $ibpEmployee->id]);
        $assessment = Assessment::query()->create(['title' => 'HI Only QA Assessment', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'applies_to_all_campaigns' => false, 'created_by' => $admin->id]);
        $assessment->campaigns()->attach($homeImprovement->id);

        $this->actingAs($admin)->post(route('assessments.assignments.store'), [
            'assessment_id' => $assessment->id,
            'employee_ids' => [$ibpEmployee->id],
            'team_ids' => [],
            'all_employees' => false,
        ])->assertStatus(422);

        $this->assertDatabaseCount('assessment_assignments', 0);
    }

    public function test_assignment_status_filter_accepts_actual_workflow_statuses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $assessment = Assessment::query()->create(['title' => 'Published', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'pending_review']);

        $this->actingAs($admin)->get(route('assessments.assignments', ['status' => 'pending_review']))->assertOk()->assertSee('pending_review', false);
        $this->actingAs($admin)->get(route('assessments.assignments', ['status' => 'completed']))->assertSessionHasErrors('status');
    }

    public function test_admin_can_edit_dates_without_changing_identity_or_attempts_and_audit_is_written(): void
    {
        [$admin, $employee, $assessment, $assignment] = $this->assignmentContext();
        $attempt = AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now(), 'status' => 'in_progress']);
        $otherEmployee = User::factory()->create(['role' => 'agent']);

        $this->actingAs($admin)->put(route('assessments.assignments.update', $assignment), [
            'available_from' => '2026-08-13 09:00:00', 'due_date' => '2026-08-15 17:00:00',
            'employee_id' => $otherEmployee->id, 'assessment_id' => 999999,
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame($employee->id, $assignment->employee_id);
        $this->assertSame($assessment->id, $assignment->assessment_id);
        $this->assertSame('2026-08-13 01:00:00', $assignment->available_from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 09:00:00', $assignment->due_date->format('Y-m-d H:i:s'));
        $this->assertSame($assignment->id, $attempt->fresh()->assignment_id);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Assessment Assignment Updated', 'target_id' => $assignment->id]);

        $this->actingAs($admin)->put(route('assessments.assignments.update', $assignment), ['available_from' => '2026-08-16', 'due_date' => '2026-08-15'])->assertSessionHasErrors('due_date');
        $this->actingAs($employee)->put(route('assessments.assignments.update', $assignment), [])->assertForbidden();
    }

    public function test_untouched_assignment_can_be_deleted_and_audited(): void
    {
        [$admin, $employee, $assessment, $assignment] = $this->assignmentContext();

        $this->actingAs($admin)->delete(route('assessments.assignments.destroy', $assignment))->assertRedirect();

        $this->assertDatabaseMissing('assessment_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Assessment Assignment Deleted', 'target_id' => $assignment->id]);
    }

    public function test_assignment_with_attempt_or_training_history_cannot_be_deleted(): void
    {
        [$admin, $employee, $assessment, $assignment] = $this->assignmentContext();
        $attempt = AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now(), 'submitted_at' => now(), 'status' => 'passed', 'points_earned' => 1, 'total_points' => 1, 'percentage' => 100, 'passed' => true]);

        $this->actingAs($admin)->delete(route('assessments.assignments.destroy', $assignment))->assertStatus(422);
        $this->assertDatabaseHas('assessment_attempts', ['id' => $attempt->id, 'percentage' => 100]);
        $this->assertDatabaseHas('assessment_assignments', ['id' => $assignment->id]);

        [$adminTwo, $employeeTwo, $assessmentTwo, $trainingAssignment] = $this->assignmentContext();
        $material = AssessmentTrainingMaterial::query()->create(['assessment_id' => $assessmentTwo->id, 'title' => 'Read', 'type' => 'written', 'is_required' => true, 'display_order' => 1, 'is_active' => true, 'created_by' => $adminTwo->id]);
        $progress = AssessmentTrainingProgress::query()->create(['material_id' => $material->id, 'employee_id' => $employeeTwo->id, 'started_at' => now()]);

        $this->actingAs($adminTwo)->delete(route('assessments.assignments.destroy', $trainingAssignment))->assertStatus(422);
        $this->assertDatabaseHas('assessment_training_progress', ['id' => $progress->id]);
        $this->actingAs($employeeTwo)->delete(route('assessments.assignments.destroy', $trainingAssignment))->assertForbidden();
    }

    public function test_assignment_index_returns_history_counts_without_loading_history_records(): void
    {
        [$admin, $employee, $assessment, $assignment] = $this->assignmentContext();
        AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now(), 'status' => 'in_progress']);

        $this->actingAs($admin)->get(route('assessments.assignments'))->assertInertia(fn ($page) => $page
            ->where('assignments.data.0.attempts_count', 1)
            ->where('assignments.data.0.training_progress_count', 0));
    }

    public function test_assignment_time_is_returned_with_the_same_wall_clock_value_that_was_submitted(): void
    {
        [$admin, $employee, $assessment] = $this->assignmentContext();
        $payload = [
            'assessment_id' => $assessment->id,
            'employee_ids' => [$employee->id],
            'team_ids' => [],
            'all_employees' => false,
            'available_from' => '2026-08-14T09:15',
            'due_date' => '2026-08-14T17:45',
        ];

        // Remove the context assignment so the normal assignment endpoint can create it.
        AssessmentAssignment::query()->delete();
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload)->assertRedirect();

        $saved = AssessmentAssignment::query()->firstOrFail();
        $this->assertSame('2026-08-14 01:15', $saved->available_from->format('Y-m-d H:i'));
        $this->assertSame('2026-08-14 09:45', $saved->due_date->format('Y-m-d H:i'));
        $this->actingAs($admin)->get(route('assessments.assignments'))->assertInertia(fn ($page) => $page
            ->where('assignments.data.0.available_from', '2026-08-14T01:15:00.000000Z')
            ->where('assignments.data.0.due_date', '2026-08-14T09:45:00.000000Z'));
    }

    private function assignmentContext(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $assessment = Assessment::query()->create(['title' => 'Published', 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => 'published', 'created_by' => $admin->id]);
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'status' => 'assigned']);

        return [$admin, $employee, $assessment, $assignment];
    }
}
