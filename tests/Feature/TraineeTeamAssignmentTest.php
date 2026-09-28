<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TraineeTeamAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $campaign = Campaign::create(['name' => 'Training', 'abbreviation' => 'TR']);
        $teams = collect(['First', 'Second', 'Outside'])->map(fn ($name) => Team::create(['name' => $name, 'campaign_id' => $campaign->id]));
        foreach ($teams as $index => $team) {
            TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $index === 2 ? $other->id : $leader->id]);
        }
        $trainee = User::factory()->create(['role' => 'trainee', 'status' => 'active', 'username' => 'DVXTR001']);
        $trainee->forceFill(['training_campaign_id' => $campaign->id, 'training_status' => 'in_training'])->save();

        return [$admin, $leader, $other, $teams, $trainee];
    }

    public function test_admin_assigns_trainee_and_leader_can_handle_attendance_and_status_only_for_own_team(): void
    {
        [$admin,$leader,$other,$teams,$trainee] = $this->fixture();
        $this->actingAs($admin)->put('/trainees/'.$trainee->id.'/team', ['team_id' => $teams[0]->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/trainees')->assertInertia(fn (Assert $page) => $page->where('trainees.data.0.teamId', $teams[0]->id)->where('trainees.data.0.leaderName', $leader->name));
        $this->actingAs($leader)->get('/my-team')->assertInertia(fn (Assert $page) => $page->has('members', 1)->where('members.0.id', $trainee->id));
        $this->get('/attendance')->assertInertia(fn (Assert $page) => $page->where('employees', fn ($users) => collect($users)->contains(fn ($user) => $user['id'] === $trainee->id && $user['canEdit'])));
        $data = ['attendance_date' => '2026-09-28', 'field' => 'time_in', 'time' => '08:00'];
        $this->put('/attendance/'.$trainee->id.'/time', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($other)->put('/attendance/'.$trainee->id.'/time', $data)->assertForbidden();
        $this->patch('/account-statuses/'.$trainee->id, ['status' => 'graduated'])->assertForbidden();
        $this->get('/my-team')->assertInertia(fn (Assert $page) => $page->has('members', 0)->has('assignableAgents', 0));
    }

    public function test_leader_can_assign_unassigned_and_move_between_owned_teams_but_cannot_take_another_leaders_trainee(): void
    {
        [$admin,$leader,$other,$teams,$trainee] = $this->fixture();
        $this->actingAs($leader);
        foreach ($teams->take(2) as $team) {
            $this->post('/my-team/agents', ['team_id' => $team->id, 'agent_id' => $trainee->id])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('team_members', ['user_id' => $trainee->id, 'team_id' => $team->id]);
        }
        $this->post('/my-team/agents', ['team_id' => $teams[2]->id, 'agent_id' => $trainee->id])->assertForbidden();
        $this->actingAs($other)->post('/my-team/agents', ['team_id' => $teams[2]->id, 'agent_id' => $trainee->id])->assertForbidden();
        $this->actingAs($trainee)->put('/trainees/'.$trainee->id.'/team', ['team_id' => $teams[2]->id])->assertForbidden();
    }

    public function test_campaign_mismatch_missing_leader_and_failed_trainee_are_rejected(): void
    {
        [$admin,$leader,$other,$teams,$trainee] = $this->fixture();
        $otherCampaign = Campaign::create(['name' => 'Other', 'abbreviation' => 'OT']);
        $wrong = Team::create(['name' => 'Wrong campaign', 'campaign_id' => $otherCampaign->id]);
        TeamLeaderAssignment::create(['team_id' => $wrong->id, 'user_id' => $leader->id]);
        $this->actingAs($admin)->put('/trainees/'.$trainee->id.'/team', ['team_id' => $wrong->id])->assertSessionHasErrors('team_id');
        $this->actingAs($leader)->post('/my-team/agents', ['team_id' => $wrong->id, 'agent_id' => $trainee->id])->assertSessionHasErrors('team_id');
        TeamLeaderAssignment::where('team_id', $teams[0]->id)->delete();
        $this->actingAs($admin)->put('/trainees/'.$trainee->id.'/team', ['team_id' => $teams[0]->id])->assertSessionHasErrors('team_id');
        $trainee->forceFill(['training_status' => 'rejected', 'status' => 'terminated'])->save();
        $this->put('/trainees/'.$trainee->id.'/team', ['team_id' => $teams[1]->id])->assertSessionHasErrors('team_id');
        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_admin_bulk_assignment_preserves_trainee_and_transfer_removes_previous_leader_access(): void
    {
        [$admin,$leader,$other,$teams,$trainee] = $this->fixture();
        $this->actingAs($admin)->post('/team-assigning', ['team_id' => $teams[0]->id, 'team_leader_id' => $leader->id, 'agent_ids' => [$trainee->id]])->assertSessionHasNoErrors();
        $this->get('/team-assigning')->assertInertia(fn (Assert $page) => $page->has('agents', 1)->where('agents.0.role', 'trainee'));
        $this->post('/team-assigning/transfer', ['team_id' => $teams[2]->id, 'agent_id' => $trainee->id])->assertSessionHasNoErrors();
        $this->actingAs($leader)->get('/my-team')->assertInertia(fn (Assert $page) => $page->has('members', 0));
        $this->actingAs($other)->get('/my-team')->assertInertia(fn (Assert $page) => $page->has('members', 1));
    }

    public function test_assigned_leader_sees_trainee_coaching_including_before_team_assignment_but_other_leaders_cannot(): void
    {
        [$admin,$leader,$other,$teams,$trainee] = $this->fixture();
        $record = CoachingRecord::create(['employee_id' => $trainee->id, 'coach_id' => $admin->id, 'campaign_id' => $trainee->training_campaign_id, 'campaign_name' => 'Training', 'team_id' => null, 'coaching_date' => '2026-09-28', 'type' => 'general', 'status' => 'open', 'summary' => 'Trainee coaching']);
        TeamMember::create(['user_id' => $trainee->id, 'team_id' => $teams[0]->id]);
        $this->actingAs($leader)->get('/management/coaching')->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
        $this->get('/management/coaching/'.$record->id)->assertOk();
        $this->actingAs($other)->get('/management/coaching')->assertInertia(fn (Assert $page) => $page->has('records.data',0));
        $this->get('/management/coaching/'.$record->id)->assertForbidden();
    }
}
