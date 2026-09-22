<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_fetch_update_and_delete_campaign_teams(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);

        $this->actingAs($admin)->post(route('teams.store'), [
            'name' => 'Internal Medicare',
            'campaign_id' => $campaign->id,
        ])->assertRedirect(route('teams'));

        $team = Team::query()->firstOrFail();
        $this->assertTrue($team->campaign->is($campaign));

        $this->actingAs($admin)->get(route('teams'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams')
                ->where('teams.0.name', 'Internal Medicare')
                ->where('teams.0.campaign', 'Inbound Pro')
                ->where('teams.0.campaignId', $campaign->id));

        $secondCampaign = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $this->actingAs($admin)->put(route('teams.update', $team), [
            'name' => 'VIP',
            'campaign_id' => $secondCampaign->id,
        ])->assertRedirect(route('teams'));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'VIP',
            'campaign_id' => $secondCampaign->id,
        ]);

        $this->actingAs($admin)->delete(route('teams.destroy', $team))->assertRedirect(route('teams'));
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_team_requires_an_existing_campaign(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);

        $this->actingAs($admin)->post(route('teams.store'), [
            'name' => 'Invalid Team',
            'campaign_id' => 99999,
        ])->assertSessionHasErrors('campaign_id');
    }

    public function test_admin_can_filter_teams_by_campaign(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $homeImprovement = Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $ibp = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        Team::query()->create(['name' => 'Upfinity', 'campaign_id' => $homeImprovement->id]);
        Team::query()->create(['name' => 'Internal Medicare', 'campaign_id' => $ibp->id]);

        $this->actingAs($admin)->get(route('teams', ['campaign' => $ibp->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams')
                ->has('teams', 1)
                ->where('teams.0.name', 'Internal Medicare')
                ->where('teams.0.campaignId', $ibp->id)
                ->where('filters.campaign', $ibp->id));
    }

    public function test_team_leader_is_an_optional_user_foreign_key(): void
    {
        $campaign = Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);
        $leader = User::factory()->create(['username' => 'DVX002', 'role' => 'team_leader']);
        $team = Team::query()->create([
            'name' => 'Internal Medicare',
            'campaign_id' => $campaign->id,
        ]);
        $assignment = $team->leaderAssignment()->create(['user_id' => $leader->id]);

        $this->assertTrue($assignment->user->is($leader));
        $leader->delete();
        $this->assertDatabaseMissing('team_leader_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_non_admin_cannot_manage_teams(): void
    {
        $employee = User::factory()->create(['username' => 'DVX002', 'role' => 'agent']);
        $this->actingAs($employee)->get(route('teams'))->assertForbidden();
        $this->actingAs($employee)->post(route('teams.store'), [
            'name' => 'Unauthorized', 'campaign_id' => 1,
        ])->assertForbidden();
        $this->assertDatabaseCount('teams', 0);
    }
}
