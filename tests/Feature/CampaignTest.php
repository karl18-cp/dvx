<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_fetch_update_and_delete_campaigns(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);

        $this->actingAs($admin)->post(route('campaigns.store'), [
            'name' => 'Home Improvement',
            'abbreviation' => 'HI',
        ])->assertRedirect(route('campaigns'));

        $campaign = Campaign::query()->firstOrFail();

        $this->actingAs($admin)->get(route('campaigns'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('campaigns')
                ->where('campaigns.0.name', 'Home Improvement')
                ->where('campaigns.0.abbreviation', 'HI'));

        $this->actingAs($admin)->put(route('campaigns.update', $campaign), [
            'name' => 'Inbound Pro',
            'abbreviation' => 'IBP',
        ])->assertRedirect(route('campaigns'));

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'Inbound Pro',
            'abbreviation' => 'IBP',
        ]);

        $this->actingAs($admin)
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect(route('campaigns'));

        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_campaign_name_and_abbreviation_must_be_unique(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        Campaign::query()->create(['name' => 'Inbound Pro', 'abbreviation' => 'IBP']);

        $this->actingAs($admin)->post(route('campaigns.store'), [
            'name' => 'Inbound Pro',
            'abbreviation' => 'IBP',
        ])->assertSessionHasErrors(['name', 'abbreviation']);
    }

    public function test_non_admin_cannot_manage_campaigns(): void
    {
        $employee = User::factory()->create(['username' => 'DVX002', 'role' => 'agent']);

        $this->actingAs($employee)->get(route('campaigns'))->assertForbidden();
        $this->actingAs($employee)->post(route('campaigns.store'), [
            'name' => 'Unauthorized',
            'abbreviation' => 'NO',
        ])->assertForbidden();

        $this->assertDatabaseCount('campaigns', 0);
    }
}
