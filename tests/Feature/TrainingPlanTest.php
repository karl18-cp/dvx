<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignSchedule;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\AttendanceScheduleService;
use App\Services\TrainingAllowanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainingPlanTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(20, 0));
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $campaign = Campaign::create(['name' => 'Training', 'abbreviation' => 'TR']);
        $trainee = User::factory()->create(['role' => 'trainee', 'status' => 'active']);
        $schedule = CampaignSchedule::create(['name' => 'Training shift']);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => false, 'time_in' => '08:00', 'time_out' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00']);
        }
        $trainee->forceFill(['training_campaign_id' => $campaign->id, 'training_status' => 'in_training', 'campaign_schedule_id' => $schedule->id])->save();
        $payload = ['name' => 'October cohort', 'campaign_id' => $campaign->id, 'start_date' => '2026-09-28', 'weekdays' => [1, 2, 3, 4, 5], 'phases' => [['days' => 3, 'rate' => '200.25'], ['days' => 5, 'rate' => '300.50'], ['days' => 4, 'rate' => '400']], 'first_allowance_day' => 10, 'allowance_basis' => 'attended', 'trainee_ids' => [$trainee->id]];

        return [$admin, $trainee, $payload];
    }

    private function attend(User $user, string $date, bool $complete = true): void
    {
        $service = app(AttendanceScheduleService::class);
        $service->record($user, $date, 'time_in', '08:00');
        if ($complete) {
            $service->record($user, $date, 'time_out', '17:00');
        }
    }

    public function test_admin_and_qa_can_manage_plans_and_other_roles_cannot(): void
    {
        [$admin, $trainee, $payload] = $this->context();
        foreach (['admin', 'qa_admin'] as $role) {
            $admin->update(['role' => $role]);
            $this->actingAs($admin)->get('/management/training-plans')->assertOk();
        }
        $this->post('/management/training-plans', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan = TrainingPlan::sole();
        $this->assertSame(20025, $plan->phases[0]['rate_cents']);
        $this->assertDatabaseHas('training_plan_enrollments', ['training_plan_id' => $plan->id, 'user_id' => $trainee->id]);
        $payload['name'] = 'Updated';
        $this->put('/management/training-plans/'.$plan->id, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Training plan updated']);
        foreach (['team_leader', 'agent', 'manager'] as $role) {
            $admin->update(['role' => $role]);
            $this->actingAs($admin)->get('/management/training-plans')->assertForbidden();
            $this->post('/management/training-plans', $payload)->assertForbidden();
            $this->put('/management/training-plans/'.$plan->id, $payload)->assertForbidden();
            $this->get("/management/training-plans/$plan->id/trainees/$trainee->id")->assertForbidden();
        }
    }

    public function test_first_allowance_sums_ten_attended_days_across_phases_and_skips_absences(): void
    {
        [$admin, $trainee, $payload] = $this->context();
        $this->actingAs($admin)->post('/management/training-plans', $payload)->assertSessionHasNoErrors();
        $plan = TrainingPlan::sole();
        $service = app(TrainingAllowanceService::class);
        $calendar = $service->calendar($plan);
        $this->assertSame('2026-10-05', $calendar[5]['date']);
        // Miss the first day; an incomplete punch is not an attended day.
        $this->attend($trainee, $calendar[0]['date'], false);
        foreach (array_slice($calendar, 1, 9) as $row) {
            $this->attend($trainee, $row['date']);
        }
        $summary = $service->summary($plan, $trainee->fresh());
        $this->assertSame(9, $summary['qualified_days']);
        $this->assertFalse($summary['first_allowance_ready']);
        $this->assertSame(2 * 20025 + 5 * 30050 + 2 * 40000, $summary['first_allowance_cents']);
        $this->assertSame(480, $summary['rows'][1]['worked_minutes']);
        $this->assertSame(0, $summary['rows'][0]['amount_cents']);
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(20, 0));
        $this->attend($trainee, $calendar[10]['date']);
        $this->attend($trainee, $calendar[11]['date']);
        $summary = $service->summary($plan, $trainee->fresh());
        $this->assertTrue($summary['first_allowance_ready']);
        $this->assertSame($calendar[10]['date'], $summary['first_allowance_date']);
        $this->assertSame(2 * 20025 + 5 * 30050 + 3 * 40000, $summary['first_allowance_cents']);
        $this->assertSame($summary['first_allowance_cents'] + 40000, $summary['earned_cents']);
    }

    public function test_trainee_sees_only_their_plan_and_graduate_keeps_history(): void
    {
        [$admin, $trainee, $payload] = $this->context();
        $this->actingAs($admin)->post('/management/training-plans', $payload);
        $plan = TrainingPlan::sole();
        $this->actingAs($trainee)->get('/my-training-plan?user_id='.$admin->id)->assertInertia(fn (Assert $p) => $p->where('allowance.trainee.id', $trainee->id)->where('plan.id', $plan->id));
        $this->get("/management/training-plans/$plan->id/trainees/$admin->id")->assertForbidden();
        $trainee->forceFill(['role' => 'agent', 'training_status' => 'graduated'])->save();
        $this->actingAs($trainee)->get('/my-training-plan')->assertOk();
        $this->actingAs($admin)->get("/management/training-plans/$plan->id/trainees/$admin->id")->assertNotFound();
        $outsider = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->actingAs($outsider)->get('/my-training-plan')->assertInertia(fn (Assert $p) => $p->where('plan', null)->where('allowance', null));
    }

    public function test_invalid_enrollments_and_plan_values_are_rejected(): void
    {
        [$admin, $trainee, $payload] = $this->context();
        $this->actingAs($admin);
        foreach ([['first_allowance_day' => 13], ['weekdays' => []], ['trainee_ids' => [$admin->id]], ['allowance_basis' => 'scheduled']] as $invalid) {
            $this->post('/management/training-plans', array_replace($payload, $invalid))->assertSessionHasErrors();
        }
        $bad = $payload;
        $bad['phases'][0]['rate'] = '-1';
        $this->post('/management/training-plans', $bad)->assertSessionHasErrors('phases.0.rate');
        $bad['phases'][0]['rate'] = '1.999';
        $this->post('/management/training-plans', $bad)->assertSessionHasErrors('phases.0.rate');
        $this->post('/management/training-plans', $payload)->assertSessionHasNoErrors();
        $this->post('/management/training-plans', $payload)->assertSessionHasErrors('trainee_ids');
        $plan = TrainingPlan::sole();
        $this->put('/management/training-plans/'.$plan->id, array_replace($payload, ['trainee_ids' => []]))->assertSessionHasErrors('trainee_ids');
    }
}
