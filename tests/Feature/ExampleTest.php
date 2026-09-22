<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_employee_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_home_logs_out_an_authenticated_user_before_showing_employee_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
