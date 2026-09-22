<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentSkill;
use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\CoachingTrainingAssignment;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TrainingLibraryMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoachingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_employee_training_profile_with_existing_skill_calculation(): void
    {
        [$manager, $employee] = $this->people();
        $skill = AssessmentSkill::query()->create(['name' => 'Objection Handling']);
        $assessment = Assessment::query()->create(['title' => 'Call Review', 'passing_score' => 80, 'maximum_attempts' => 2, 'status' => 'published', 'created_by' => $manager->id]);
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $manager->id, 'assigned_at' => now(), 'status' => 'passed']);
        $attempt = AssessmentAttempt::query()->create(['assessment_id' => $assessment->id, 'assignment_id' => $assignment->id, 'employee_id' => $employee->id, 'attempt_number' => 1, 'question_snapshot' => [], 'started_at' => now()->subMinutes(5), 'submitted_at' => now(), 'status' => 'passed', 'percentage' => 90, 'passed' => true]);
        $attempt->skillResults()->create(['skill_id' => $skill->id, 'points_earned' => 9, 'points_possible' => 10, 'percentage' => 90]);

        $this->actingAs($manager)->get(route('coaching.profile', $employee))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('coaching/profile')->where('summary.assessments_completed', 1)->where('summary.average_score', 90)->where('skills.0.name', 'Objection Handling')->where('skills.0.status', 'Strong')->has('recentAssessments', 1));
    }

    public function test_employee_cannot_view_management_profile_or_create_coaching(): void
    {
        [, $employee] = $this->people();
        $other = User::factory()->create(['role' => 'agent']);

        $this->actingAs($employee)->get(route('coaching.profile', $other))->assertForbidden();
        $this->actingAs($employee)->post(route('coaching.manage.store'), $this->validData($other))->assertForbidden();
        $this->assertDatabaseCount('coaching_records', 0);
    }

    public function test_manager_creates_linked_coaching_with_shared_training_and_notifications_once(): void
    {
        [$manager, $employee] = $this->people();
        $skill = AssessmentSkill::query()->create(['name' => 'Grammar']);
        $material = $this->material($manager, $skill);

        $this->actingAs($manager)->post(route('coaching.manage.store'), [...$this->validData($employee), 'skill_id' => $skill->id, 'material_ids' => [$material->id]])->assertRedirect();
        $record = CoachingRecord::query()->firstOrFail();
        $this->assertDatabaseHas('coaching_training_assignments', ['coaching_record_id' => $record->id, 'library_material_id' => $material->id]);
        $this->assertDatabaseCount('training_library_materials', 1);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $employee->id, 'type' => 'coaching_assigned']);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $employee->id, 'type' => 'coaching_training_assigned']);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Coaching Record Created', 'target_id' => $record->id]);
    }

    public function test_employee_access_acknowledgment_and_training_progress_are_ownership_scoped(): void
    {
        [$manager, $employee] = $this->people();
        $other = User::factory()->create(['role' => 'agent']);
        $record = CoachingRecord::query()->create([...$this->validData($employee), 'coach_id' => $manager->id, 'status' => 'open']);
        $assignment = CoachingTrainingAssignment::query()->create(['coaching_record_id' => $record->id, 'library_material_id' => $this->material($manager)->id, 'is_required' => true, 'assigned_by' => $manager->id]);

        $this->actingAs($other)->get(route('coaching.my.show', $record))->assertForbidden();
        $this->actingAs($other)->post(route('coaching.my.acknowledge', $record))->assertForbidden();
        $this->actingAs($employee)->post(route('coaching.my.acknowledge', $record))->assertRedirect();
        $acknowledged = $record->fresh()->acknowledged_at;
        $this->actingAs($employee)->post(route('coaching.my.acknowledge', $record))->assertRedirect();
        $this->assertTrue($acknowledged->equalTo($record->fresh()->acknowledged_at));
        $this->actingAs($employee)->post(route('coaching.my.training.complete', [$record, $assignment]))->assertRedirect();
        $this->assertDatabaseHas('coaching_training_progress', ['coaching_training_assignment_id' => $assignment->id, 'employee_id' => $employee->id, 'completion_percentage' => 100]);
        $this->assertDatabaseCount('assessment_training_progress', 0);
        $this->assertDatabaseCount('assessment_training_attachment_progress', 0);
    }

    public function test_required_training_blocks_completion_then_completed_record_is_read_only(): void
    {
        [$manager, $employee] = $this->people();
        $record = CoachingRecord::query()->create([...$this->validData($employee), 'coach_id' => $manager->id, 'status' => 'open']);
        $assignment = CoachingTrainingAssignment::query()->create(['coaching_record_id' => $record->id, 'library_material_id' => $this->material($manager)->id, 'is_required' => true, 'assigned_by' => $manager->id]);

        $this->actingAs($manager)->post(route('coaching.manage.complete', $record))->assertRedirect()->assertSessionHasErrors('completion');
        $this->actingAs($employee)->post(route('coaching.my.training.complete', [$record, $assignment]));
        $this->actingAs($manager)->post(route('coaching.manage.complete', $record))->assertRedirect();
        $this->assertDatabaseHas('coaching_records', ['id' => $record->id, 'status' => 'completed', 'completed_by' => $manager->id]);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Coaching Completed', 'target_id' => $record->id]);
        $this->actingAs($manager)->put(route('coaching.manage.update', $record), $this->validData($employee))->assertStatus(422);
    }

    public function test_coaching_keeps_original_team_and_campaign_after_employee_transfer(): void
    {
        [$manager, $employee] = $this->people();
        $employee->teamMembership()->delete();
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $originalCampaign = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $newCampaign = Campaign::query()->create(['name' => 'IBP', 'abbreviation' => 'IBP']);
        $originalTeam = Team::query()->create(['name' => 'HI Team', 'campaign_id' => $originalCampaign->id]);
        $newTeam = Team::query()->create(['name' => 'IBP Team', 'campaign_id' => $newCampaign->id]);
        TeamMember::query()->create(['team_id' => $originalTeam->id, 'user_id' => $employee->id]);

        $this->actingAs($manager)->post(route('coaching.manage.store'), $this->validData($employee))->assertRedirect();
        $record = CoachingRecord::query()->firstOrFail();

        $this->actingAs($manager)->post(route('team-assigning.store'), [
            'team_id' => $newTeam->id,
            'team_leader_id' => $leader->id,
            'agent_ids' => [$employee->id],
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame($originalTeam->id, $record->team_id);
        $this->assertSame('HI Team', $record->team_name);
        $this->assertSame($originalCampaign->id, $record->campaign_id);
        $this->assertSame('Home Improvement', $record->campaign_name);
        $this->assertSame($newTeam->id, $employee->fresh()->teamMembership->team_id);
        $this->actingAs($manager)->get(route('coaching.manage.index'))->assertInertia(fn (Assert $page) => $page->where('records.data.0.team_name', 'HI Team'));
    }

    private function people(): array
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'username' => 'DVX900', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => 'QA Campaign '.uniqid(), 'abbreviation' => 'QA'.uniqid()]);
        $team = Team::query()->create(['name' => 'QA Team '.uniqid(), 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $employee->id]);

        return [$manager, $employee];
    }

    private function validData(User $employee): array
    {
        return ['employee_id' => $employee->id, 'type' => 'General Coaching', 'coaching_date' => now()->toDateString(), 'summary' => 'Review current performance.', 'strengths' => 'Professional tone.', 'areas_for_improvement' => 'Probing questions.', 'action_plan' => 'Review assigned lesson.', 'follow_up_date' => now()->addWeek()->toDateString()];
    }

    private function material(User $manager, ?AssessmentSkill $skill = null): TrainingLibraryMaterial
    {
        return TrainingLibraryMaterial::query()->create(['title' => 'Handling Objections', 'description' => 'Practice lesson', 'type' => 'written', 'content' => 'Read this lesson.', 'skill_id' => $skill?->id, 'status' => 'active', 'created_by' => $manager->id]);
    }
}
