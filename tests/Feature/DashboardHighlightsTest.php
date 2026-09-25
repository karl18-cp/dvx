<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\EmployeeForm;
use App\Models\EmployeeFormResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardHighlightsTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $birth, string $start, string $status = 'active'): User
    {
        $user = User::factory()->create(['status' => $status]);
        $user->personalInformation()->create(['email' => $user->email, 'birth_date' => $birth, 'start_date' => $start, 'gender' => 'Other', 'civil_status' => 'Single', 'phone' => '123', 'address' => 'Private address', 'emergency_contact_name' => 'Private contact', 'emergency_contact_relationship' => 'Parent', 'emergency_contact_phone' => '456']);

        return $user;
    }

    public function test_top_five_matches_ranking_and_latest_announcements_are_limited(): void
    {
        $form = EmployeeForm::create(['title' => 'Points', 'fields' => []]);
        $users = collect();
        foreach ([2, 6, 4, 6, 1, 3] as $points) {
            $user = User::factory()->create();
            $users->push($user);
            for ($i = 0; $i < $points; $i++) {
                EmployeeFormResponse::create(['request_id' => Str::uuid(), 'employee_form_id' => $form->id, 'employee_id' => $user->id, 'employee_name' => $user->name, 'form_title' => 'Points', 'revision' => 1, 'teams' => [], 'answers' => [], 'points' => 1]);
            }
        }
        for ($i = 1; $i <= 6; $i++) {
            Announcement::create(['title' => "Update $i", 'body' => 'News', 'author_name' => 'Admin']);
        }
        $expected = [$users[1]->id, $users[3]->id, $users[2]->id, $users[5]->id, $users[0]->id];
        $this->actingAs($users[0])->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard')->has('leaders', 5)->where('leaders', fn ($rows) => $rows->pluck('id')->all() === $expected)->where('leaders.0.points', 6)->has('announcements', 5)->where('announcements.0.title', 'Update 6'));
        $this->get('/ranking')->assertInertia(fn (Assert $page) => $page->where('leaders', fn ($rows) => $rows->take(5)->pluck('id')->all() === $expected));
    }

    public function test_celebrations_cross_year_boundary_and_do_not_expose_private_details(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-20 23:30:00', 'Asia/Manila'));
        $today = $this->employee('1990-12-20', '2023-12-20');
        $next = $this->employee('1995-01-02', '2025-01-02');
        $this->employee('1990-01-20', '2023-01-20');
        $this->employee('1990-12-21', '2023-12-21', 'resigned');
        $this->employee('1990-06-01', '2026-12-20');
        $this->actingAs($today)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('today', '2026-12-20')->has('birthdays', 2)->where('birthdays.0.id', $today->id)->where('birthdays.0.daysAway', 0)->where('birthdays.1.date', '2027-01-02')->missing('birthdays.0.birth_date')->missing('birthdays.0.years')->missing('birthdays.0.address')->has('anniversaries', 2)->where('anniversaries.0.years', 3)->where('anniversaries.1.id', $next->id)->where('anniversaries.1.years', 2));
    }

    public function test_leap_day_celebrations_use_february_28_in_non_leap_years(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-02-28 12:00:00', 'Asia/Manila'));
        $user = $this->employee('2000-02-29', '2024-02-29');
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('birthdays.0.date', '2027-02-28')->where('birthdays.0.daysAway', 0)->where('anniversaries.0.years', 3));
    }
}
