<?php

namespace Tests\Feature;

use App\Models\CampaignSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $name = 'Morning shift'): array
    {
        return ['name' => $name, 'days' => array_map(fn ($day) => [
            'day' => $day, 'no_schedule' => $day > 5,
            'time_in' => $day > 5 ? null : '08:00', 'time_out' => $day > 5 ? null : '17:00',
            'break_start' => $day > 5 ? null : '12:00', 'break_end' => $day > 5 ? null : '13:00',
        ], range(1, 7))];
    }

    public function test_admin_can_create_edit_and_delete_a_complete_weekly_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/campaign-schedules')->assertOk()->assertInertia(fn (Assert $page) => $page->component('campaign-schedules')->has('schedules', 0));
        $this->post('/campaign-schedules', $this->payload())->assertRedirect('/campaign-schedules?tab=view');
        $schedule = CampaignSchedule::query()->sole();
        $this->assertCount(7, $schedule->days);
        $this->assertDatabaseHas('campaign_schedule_days', ['campaign_schedule_id' => $schedule->id, 'day' => 6, 'no_schedule' => true, 'time_in' => null]);
        $this->get('/campaign-schedules')->assertInertia(fn (Assert $page) => $page->where('schedules.0.days.0.time_in', '08:00')->where('schedules.0.days.5.time_in', ''));

        $employee = User::factory()->create(['username' => 'SCHED001']);
        $this->put("/campaign-schedules/$schedule->id/employees", ['employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $this->put("/campaign-schedules/$schedule->id", $this->payload('Updated shift'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('campaign_schedule_days', 7);
        $this->get('/employees')->assertInertia(fn (Assert $page) => $page->where('employees.0.schedule', 'Updated shift'));
        $this->delete("/campaign-schedules/$schedule->id")->assertRedirect();
        $this->assertDatabaseCount('campaign_schedules', 0);
        $this->assertDatabaseCount('campaign_schedule_days', 0);
        $this->assertNull($employee->fresh()->campaign_schedule_id);
        $this->assertDatabaseHas('users', ['id' => $employee->id]);
    }

    public function test_employee_assignments_can_be_moved_and_removed_without_duplicates(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $employee = User::factory()->create(['role' => 'agent']);
        $other = User::factory()->create(['role' => 'team_leader']);
        $this->actingAs($manager)->post('/campaign-schedules', $this->payload('First'));
        $first = CampaignSchedule::query()->sole();
        $this->post('/campaign-schedules', $this->payload('Second'));
        $second = CampaignSchedule::query()->latest('id')->first();
        $this->put("/campaign-schedules/$first->id/employees", ['employee_ids' => [$employee->id, $other->id]])->assertSessionHasNoErrors();
        $this->put("/campaign-schedules/$second->id/employees", ['employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $this->assertSame($second->id, $employee->fresh()->campaign_schedule_id);
        $this->assertSame($first->id, $other->fresh()->campaign_schedule_id);
        $this->get('/campaign-schedules')->assertInertia(fn (Assert $page) => $page->where('schedules.0.employees_count', 1)->where('schedules.1.employees_count', 1));
        $this->put("/campaign-schedules/$second->id/employees", ['employee_ids' => []])->assertSessionHasNoErrors();
        $this->assertNull($employee->fresh()->campaign_schedule_id);
        $this->put("/campaign-schedules/$first->id/employees", ['employee_ids' => [$other->id, $other->id]])->assertSessionHasErrors('employee_ids.0');
        $this->put("/campaign-schedules/$first->id/employees", ['employee_ids' => [999999]])->assertSessionHasErrors('employee_ids.0');
        $this->assertSame($first->id, $other->fresh()->campaign_schedule_id);
    }

    public function test_overnight_shifts_and_breaks_are_saved_and_invalid_times_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = $this->payload('Night shift');
        foreach ($data['days'] as &$day) {
            if (! $day['no_schedule']) {
                $day = [...$day, 'time_in' => '22:00', 'time_out' => '06:00', 'break_start' => '01:00', 'break_end' => '02:00'];
            }
        }
        unset($day);
        $this->post('/campaign-schedules', $data)->assertSessionHasNoErrors();
        $schedule = CampaignSchedule::query()->sole();
        $invalid = $data;
        $invalid['days'][0]['break_end'] = '07:00';
        $this->put("/campaign-schedules/$schedule->id", $invalid)->assertSessionHasErrors('days.0.break_end');
        $invalid = $data;
        $invalid['days'][0]['time_out'] = '22:00';
        $this->put("/campaign-schedules/$schedule->id", $invalid)->assertSessionHasErrors('days.0.time_out');
        $invalid = $data;
        $invalid['days'][0]['break_start'] = null;
        $this->put("/campaign-schedules/$schedule->id", $invalid)->assertSessionHasErrors('days.0.break_end');
        $invalid = $data;
        $invalid['days'][0]['time_in'] = null;
        $this->put("/campaign-schedules/$schedule->id", $invalid)->assertSessionHasErrors('days.0.time_in');
        $invalid = $data;
        $invalid['days'][0]['time_in'] = '25:00';
        $this->put("/campaign-schedules/$schedule->id", $invalid)->assertSessionHasErrors('days.0.time_in');
        $this->assertDatabaseHas('campaign_schedule_days', ['campaign_schedule_id' => $schedule->id, 'day' => 1, 'time_in' => '22:00', 'time_out' => '06:00']);
    }

    public function test_week_structure_names_and_sunday_overnight_overlap_are_validated(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = $this->payload();
        $this->post('/campaign-schedules', $data)->assertSessionHasNoErrors();
        $this->post('/campaign-schedules', $data)->assertSessionHasErrors('name');
        $data['name'] = '   ';
        $this->post('/campaign-schedules', $data)->assertSessionHasErrors('name');
        $data = $this->payload('Duplicate day');
        $data['days'][6]['day'] = 1;
        $this->post('/campaign-schedules', $data)->assertSessionHasErrors('days.6.day');
        $data = $this->payload('Incomplete');
        array_pop($data['days']);
        $this->post('/campaign-schedules', $data)->assertSessionHasErrors('days');
        $data = $this->payload('Overlap');
        $data['days'][6] = ['day' => 7, 'no_schedule' => false, 'time_in' => '22:00', 'time_out' => '09:00', 'break_start' => null, 'break_end' => null];
        $this->post('/campaign-schedules', $data)->assertSessionHasErrors('days.6.time_out');
        $this->assertDatabaseCount('campaign_schedules', 1);
    }

    public function test_inactive_employees_cannot_be_newly_assigned_but_can_be_retained_or_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $inactive = User::factory()->create(['role' => 'agent', 'status' => 'resigned']);
        $this->actingAs($admin)->post('/campaign-schedules', $this->payload());
        $schedule = CampaignSchedule::query()->sole();
        $url = "/campaign-schedules/$schedule->id/employees";
        $this->put($url, ['employee_ids' => [$inactive->id]])->assertSessionHasErrors('employee_ids.0');
        $this->put($url, ['employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $employee->update(['status' => 'resigned']);
        $this->put($url, ['employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $this->put($url, ['employee_ids' => []])->assertSessionHasNoErrors();
        $this->assertNull($employee->fresh()->campaign_schedule_id);
    }

    public function test_other_roles_and_guests_cannot_read_or_modify_schedules(): void
    {
        $schedule = CampaignSchedule::query()->create(['name' => 'Protected']);
        $this->get('/campaign-schedules')->assertRedirect('/login');
        foreach (['agent', 'team_leader', 'it_admin'] as $role) {
            $employee = User::factory()->create(['role' => $role]);
            $this->actingAs($employee)->get('/campaign-schedules')->assertForbidden();
            $this->post('/campaign-schedules', $this->payload())->assertForbidden();
            $this->put("/campaign-schedules/$schedule->id", $this->payload())->assertForbidden();
            $this->put("/campaign-schedules/$schedule->id/employees", ['employee_ids' => [$employee->id]])->assertForbidden();
            $this->delete("/campaign-schedules/$schedule->id")->assertForbidden();
        }
        $this->assertDatabaseCount('campaign_schedules', 1);
    }
}
