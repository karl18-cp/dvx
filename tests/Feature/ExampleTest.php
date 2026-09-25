<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_careers(): void
    {
        $this->get(route('home'))->assertRedirect(route('careers'));
    }

    public function test_home_logs_out_an_authenticated_user_before_showing_careers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('careers'));

        $this->assertGuest();
    }
}
