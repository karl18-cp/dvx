<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_leader_can_handle_multiple_teams_and_agents_are_linked_to_one_team(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $leader = User::factory()->create(['username' => 'DVX002', 'role' => 'team_leader', 'status' => 'active']);
        $agent = User::factory()->create(['username' => 'DVX003', 'role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $firstTeam = Team::query()->create(['name' => 'Internal Medicare', 'campaign_id' => $campaign->id]);
        $secondTeam = Team::query()->create(['name' => 'Internal EBR', 'campaign_id' => $campaign->id]);

        $this->actingAs($admin)->post(route('team-assigning.store'), [
            'team_id' => $firstTeam->id,
            'team_leader_id' => $leader->id,
            'agent_ids' => [$agent->id],
        ])->assertRedirect(route('team-assigning'));

        $this->actingAs($admin)->post(route('team-assigning.store'), [
            'team_id' => $secondTeam->id,
            'team_leader_id' => $leader->id,
            'agent_ids' => [],
        ])->assertRedirect(route('team-assigning'));

        $this->assertSame(2, TeamLeaderAssignment::query()->where('user_id', $leader->id)->count());
        $this->assertDatabaseHas('team_members', ['team_id' => $firstTeam->id, 'user_id' => $agent->id]);
        $this->assertTrue($agent->fresh()->teamMembership->team->is($firstTeam));
    }

    public function test_admin_can_transfer_an_agent_to_another_team(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $leader = User::factory()->create(['username' => 'DVX002', 'role' => 'team_leader', 'status' => 'active']);
        $agent = User::factory()->create(['username' => 'DVX003', 'role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $firstTeam = Team::query()->create(['name' => 'Internal Medicare', 'campaign_id' => $campaign->id]);
        $secondTeam = Team::query()->create(['name' => 'Internal EBR', 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['team_id' => $firstTeam->id, 'user_id' => $agent->id]);

        $this->actingAs($admin)->post(route('team-assigning.store'), [
            'team_id' => $secondTeam->id,
            'team_leader_id' => $leader->id,
            'agent_ids' => [$agent->id],
        ])->assertRedirect(route('team-assigning'))
            ->assertSessionHas('status', 'Team assignment saved successfully. 1 agent(s) transferred from their previous team.');

        $this->assertDatabaseMissing('team_members', ['team_id' => $firstTeam->id, 'user_id' => $agent->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $secondTeam->id, 'user_id' => $agent->id]);
        $this->assertSame($secondTeam->id, $agent->fresh()->teamMembership->team_id);
    }

    public function test_assignment_page_fetches_current_agent_team_and_campaign(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $agent = User::factory()->create(['username' => 'DVX003', 'role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $team = Team::query()->create(['name' => 'Internal Medicare', 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);

        $this->actingAs($admin)->get(route('team-assigning'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('team-assigning')
                ->where('agents.0.currentTeam', 'Internal Medicare')
                ->where('agents.0.currentCampaign', 'Inbound Pro'));
    }

    public function test_dedicated_transfer_moves_only_selected_agent_and_writes_audit_log(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $agent = User::factory()->create(['username' => 'DVX003', 'role' => 'agent', 'status' => 'active']);
        $existingDestinationAgent = User::factory()->create(['username' => 'DVX004', 'role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $firstTeam = Team::query()->create(['name' => 'Internal Medicare', 'campaign_id' => $campaign->id]);
        $secondTeam = Team::query()->create(['name' => 'Internal EBR', 'campaign_id' => $campaign->id]);
        TeamMember::query()->create(['team_id' => $firstTeam->id, 'user_id' => $agent->id]);
        TeamMember::query()->create(['team_id' => $secondTeam->id, 'user_id' => $existingDestinationAgent->id]);

        $this->actingAs($admin)->post(route('team-assigning.transfer'), [
            'team_id' => $secondTeam->id,
            'agent_id' => $agent->id,
        ])->assertRedirect()->assertSessionHas('status', "{$agent->name} transferred to {$secondTeam->name}. Historical records were preserved.");

        $this->assertDatabaseMissing('team_members', ['team_id' => $firstTeam->id, 'user_id' => $agent->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $secondTeam->id, 'user_id' => $agent->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $secondTeam->id, 'user_id' => $existingDestinationAgent->id]);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Employee Team Transferred', 'target_type' => User::class, 'target_id' => $agent->id]);
    }

    public function test_non_admin_cannot_manage_team_assignments(): void
    {
        $agent = User::factory()->create(['username' => 'DVX003', 'role' => 'agent']);
        $this->actingAs($agent)->get(route('team-assigning'))->assertForbidden();
        $this->actingAs($agent)->post(route('team-assigning.store'), [])->assertForbidden();
    }
}
