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

class EmployeeRoleTest extends TestCase
{
    use RefreshDatabase;

    private function team(): Team
    {
        $campaign = Campaign::query()->create(['name' => 'Role test campaign', 'abbreviation' => 'ROLE']);

        return Team::query()->create(['name' => 'Role test team', 'campaign_id' => $campaign->id]);
    }

    public function test_admin_can_promote_an_agent_without_resubmitting_personal_information(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['username' => 'ROLE001', 'role' => 'agent', 'team' => 'Old team']);
        $password = $agent->password;
        $team = $this->team();
        TeamMember::query()->create(['user_id' => $agent->id, 'team_id' => $team->id]);
        $schedule = CampaignSchedule::query()->create(['name' => 'Morning']);
        $schedule->employees()->save($agent);

        $this->actingAs($admin)->patch(route('employees.update-role', $agent), ['position' => 'Team Leader'])
            ->assertRedirect(route('employees'))->assertSessionHasNoErrors();
        $agent->refresh();
        $this->assertSame('team_leader', $agent->role);
        $this->assertSame($password, $agent->password);
        $this->assertSame($schedule->id, $agent->campaign_schedule_id);
        $this->assertNull($agent->team);
        $this->assertDatabaseMissing('team_members', ['user_id' => $agent->id]);
        $this->assertDatabaseCount('personal_information', 0);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Employee Role Changed', 'target_id' => $agent->id]);
        $this->get('/employees')->assertInertia(fn (Assert $page) => $page->where('employees.0.position', 'Team Leader'));
        $this->get('/team-assigning')->assertInertia(fn (Assert $page) => $page->where('teamLeaders.0.id', $agent->id)->has('agents', 0));
        $this->actingAs($agent)->get('/management/qa-dashboard')->assertOk();
        $this->get('/management/assessments')->assertForbidden();
    }

    public function test_demoting_a_team_leader_removes_leadership_but_keeps_team_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $leader = User::factory()->create(['role' => 'team_leader']);
        $agent = User::factory()->create(['role' => 'agent']);
        $team = $this->team();
        TeamLeaderAssignment::query()->create(['team_id' => $team->id, 'user_id' => $leader->id]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);

        $this->actingAs($admin)->patch(route('employees.update-role', $leader), ['position' => 'Agent'])->assertSessionHasNoErrors();
        $this->assertSame('agent', $leader->fresh()->role);
        $this->assertDatabaseMissing('team_leader_assignments', ['user_id' => $leader->id]);
        $this->assertDatabaseHas('team_members', ['user_id' => $agent->id, 'team_id' => $team->id]);
        $this->actingAs($leader->fresh())->get('/management/qa-dashboard')->assertForbidden();
    }

    public function test_saving_the_same_role_keeps_existing_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);
        $team = $this->team();
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $this->actingAs($admin)->patch(route('employees.update-role', $agent), ['position' => 'Agent'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('team_members', ['user_id' => $agent->id, 'team_id' => $team->id]);
        $this->assertDatabaseCount('assessment_activity_logs', 0);
    }

    public function test_only_administrators_can_change_roles_and_invalid_roles_are_rejected(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $this->patch(route('employees.update-role', $agent), ['position' => 'Admin'])->assertRedirect('/login');
        foreach (['agent', 'team_leader', 'manager', 'it_admin'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->patch(route('employees.update-role', $agent), ['position' => 'Admin'])->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('employees.update-role', $agent), ['position' => 'Super Admin'])->assertSessionHasErrors('position');
        $this->assertSame('agent', $agent->fresh()->role);
        $this->patch(route('employees.update-role', $agent), ['position' => 'Manager'])->assertSessionHasNoErrors();
        $this->assertSame('manager', $agent->fresh()->role);
    }

    public function test_admin_cannot_remove_their_own_role_through_either_editor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('employees.update-role', $admin), ['position' => 'Agent'])->assertSessionHasErrors('position');
        $this->put(route('employees.update', $admin), [
            'full_name' => 'Changed', 'position' => 'Agent', 'email' => $admin->email, 'status' => 'active',
            'birth_date' => '1990-01-01', 'start_date' => '2026-01-01', 'gender' => 'Female', 'civil_status' => 'Single',
            'phone' => '09170000000', 'address' => 'Manila', 'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Sibling', 'emergency_contact_phone' => '09170000001',
        ])->assertSessionHasErrors('position');
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertNotSame('Changed', $admin->fresh()->name);
    }
}
