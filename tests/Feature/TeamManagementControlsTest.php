<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignSchedule;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamManagementControlsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $outside = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::create(['name' => 'Test', 'abbreviation' => 'T']);
        $teams = collect(['One', 'Two', 'Other'])->map(fn ($name) => Team::create(['name' => $name, 'campaign_id' => $campaign->id]));
        foreach ($teams->take(2) as $team) {
            TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        }
        TeamMember::create(['team_id' => $teams[2]->id, 'user_id' => $outside->id]);

        return [$leader, $agent, $outside, $teams];
    }

    public function test_assignments_allow_unassigned_and_own_teams_but_not_other_teams(): void
    {
        [$leader, $agent, $outside, $teams] = $this->context();
        $this->actingAs($leader)->get('/my-team')->assertInertia(fn (Assert $p) => $p->has('assignableAgents', 1)->where('assignableAgents.0.id', $agent->id));
        foreach ($teams->take(2) as $team) {
            $this->post('/my-team/agents', ['team_id' => $team->id, 'agent_id' => $agent->id])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('team_members', ['user_id' => $agent->id, 'team_id' => $team->id]);
        }
        $this->post('/my-team/agents', ['team_id' => $teams[0]->id, 'agent_id' => $outside->id])->assertForbidden();
        $this->post('/my-team/agents', ['team_id' => $teams[2]->id, 'agent_id' => $agent->id])->assertForbidden();
        $this->post('/my-team/agents', ['team_id' => $teams[0]->id, 'agent_id' => $leader->id])->assertForbidden();
        $this->actingAs($agent)->post('/my-team/agents', ['team_id' => $teams[0]->id, 'agent_id' => $agent->id])->assertForbidden();
    }

    public function test_personal_schedule_isolated_from_shared_template_and_validated(): void
    {
        [$leader, $agent] = $this->context();
        $schedule = CampaignSchedule::create(['name' => 'Shared']);
        $days = array_map(fn ($day) => ['day' => $day, 'no_schedule' => false, 'time_in' => '08:00', 'time_out' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00'], range(1, 7));
        $schedule->days()->createMany($days);
        foreach ([$leader, $agent] as $user) {
            $user->forceFill(['campaign_schedule_id' => $schedule->id])->save();
        }
        $days[0]['time_in'] = '09:00';
        $this->actingAs($leader)->put('/my-team/schedule', ['days' => $days])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotEquals($schedule->id, $leader->fresh()->campaign_schedule_id);
        $this->assertEquals($schedule->id, $agent->fresh()->campaign_schedule_id);
        $this->assertStringStartsWith('08:00', $schedule->days()->where('day', 1)->first()->time_in);
        $this->assertStringStartsWith('09:00', $leader->fresh()->campaignSchedule->days()->where('day', 1)->first()->time_in);
        $days[0]['break_end'] = '23:00';
        $this->put('/my-team/schedule', ['days' => $days])->assertSessionHasErrors('days.0.break_end');
        $this->actingAs($agent)->put('/my-team/schedule', ['days' => $days])->assertForbidden();
    }

    public function test_attendance_corrections_are_scoped_audited_and_admin_can_edit(): void
    {
        [$leader, $agent, $outside, $teams] = $this->context();
        TeamMember::create(['user_id' => $agent->id, 'team_id' => $teams[0]->id]);
        $data = ['attendance_date' => '2026-09-28', 'field' => 'time_in', 'time' => '08:00'];
        $this->actingAs($leader)->put("/attendance/$agent->id/time", $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessment_activity_logs', ['actor_id' => $leader->id, 'target_id' => $agent->id, 'action' => 'Attendance time corrected']);
        foreach ([$leader, $outside] as $user) {
            $this->put("/attendance/$user->id/time", $data)->assertForbidden();
        }
        $this->actingAs($agent)->put("/attendance/$agent->id/time", $data)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->put("/attendance/$outside->id/time", $data)->assertRedirect()->assertSessionHasNoErrors();
    }
}
