<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\Campaign;
use App\Models\CampaignSchedule;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\AttendanceScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(20, 0));
    }

    private function actor(string $role = 'accounting'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function payroll(User $employee, array $extra = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'user_id' => $employee->id, 'description' => 'September payroll', 'period_start' => '2026-09-01', 'period_end' => '2026-09-15', 'gross' => '15000.25', 'deduction' => '200.10', 'notes' => 'Reviewed deduction'], ...$extra];
    }

    private function training(): array
    {
        $campaign = Campaign::create(['name' => 'Training', 'abbreviation' => 'TR']);
        $trainee = $this->actor('trainee');
        $schedule = CampaignSchedule::create(['name' => 'Training shift']);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => false, 'time_in' => '08:00', 'time_out' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00']);
        }
        $trainee->forceFill(['training_campaign_id' => $campaign->id, 'training_status' => 'in_training', 'campaign_schedule_id' => $schedule->id])->save();
        $plan = TrainingPlan::create(['name' => 'Test plan', 'campaign_id' => $campaign->id, 'start_date' => '2026-09-28', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'phases' => [['days' => 2, 'rate_cents' => 20025], ['days' => 1, 'rate_cents' => 30050], ['days' => 1, 'rate_cents' => 40000]], 'first_allowance_day' => 2, 'allowance_basis' => 'attended']);
        $plan->trainees()->attach($trainee->id);

        return [$plan, $trainee];
    }

    private function attend(User $trainee, string $day): void
    {
        app(AttendanceScheduleService::class)->record($trainee, $day, 'time_in', '08:00');
        app(AttendanceScheduleService::class)->record($trainee, $day, 'time_out', '17:00');
    }

    public function test_portal_is_private_and_accounting_role_is_restricted(): void
    {
        $this->get('/accounting')->assertRedirect('/login');
        foreach (['admin', 'accounting'] as $role) {
            $this->actingAs($this->actor($role))->get('/accounting')->assertOk();
        }
        $this->get('/dashboard')->assertRedirect('/accounting');
        foreach (['/employees', '/trainees', '/management/qa-dashboard', '/management/training-plans', '/attendance', '/my-team', '/divertext'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/settings/profile')->assertOk();
        foreach (['agent', 'team_leader', 'manager', 'qa_admin', 'trainee'] as $role) {
            $this->actingAs($this->actor($role))->getJson('/accounting')->assertForbidden();
        }
    }

    public function test_admin_can_assign_accounting_role(): void
    {
        $employee = $this->actor('agent');
        $this->actingAs($this->actor('admin'))->patch('/employees/'.$employee->id.'/role', ['position' => 'Accounting'])->assertSessionHasNoErrors();
        $this->assertSame('accounting', $employee->fresh()->role);
    }

    public function test_financial_history_blocks_account_deletion_and_inactive_accounting_access(): void
    {
        $accountant = $this->actor();
        $employee = $this->actor('agent');
        $this->actingAs($accountant)->post('/accounting/payroll', $this->payroll($employee));
        $this->delete('/settings/profile', ['password' => 'password'])->assertForbidden();
        $this->actingAs($employee)->delete('/settings/profile', ['password' => 'password'])->assertForbidden();
        $accountant->update(['status' => 'suspended']);
        $this->actingAs($accountant)->get('/accounting')->assertRedirect('/login');
    }

    public function test_payroll_uses_exact_money_and_prevents_overlap_and_request_replay(): void
    {
        $employee = $this->actor('agent');
        $data = $this->payroll($employee);
        $this->actingAs($this->actor())->post('/accounting/payroll', $data)->assertSessionHasNoErrors();
        $entry = AccountingEntry::sole();
        $this->assertSame(1480015, $entry->net_cents);
        $this->post('/accounting/payroll', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounting_entries', 1);
        $this->post('/accounting/payroll', $this->payroll($employee, ['period_start' => '2026-09-10']))->assertSessionHasErrors('period_start');
        $this->post('/accounting/payroll', $this->payroll($employee, ['gross' => '-1']))->assertSessionHasErrors('gross');
    }

    public function test_payment_requires_approval_reference_and_is_immutable(): void
    {
        $this->actingAs($this->actor())->post('/accounting/payroll', $this->payroll($this->actor('agent')));
        $entry = AccountingEntry::sole();
        $url = '/accounting/entries/'.$entry->id;
        $payment = ['action' => 'pay', 'paid_at' => '2026-10-01', 'payment_method' => 'cash', 'payment_reference' => 'Voucher 001'];
        $this->patch($url, $payment)->assertSessionHasErrors('entry');
        $this->patch($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->patch($url, [...$payment, 'payment_reference' => ''])->assertSessionHasErrors('payment_reference');
        $this->patch($url, $payment)->assertSessionHasNoErrors();
        $this->assertSame('paid', $entry->fresh()->status);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $entry->user_id, 'deduplication_key' => 'accounting:'.$entry->id.':paid']);
        $this->patch($url, $payment)->assertSessionHasErrors('entry');
        $this->patch($url, ['action' => 'void', 'void_reason' => 'Changing history'])->assertSessionHasErrors('entry');
        $this->getJson($url)->assertOk()->assertJsonPath('payment_reference', 'Voucher 001');
        $this->actingAs($this->actor('agent'))->getJson($url)->assertForbidden();
        $this->assertDatabaseHas('assessment_activity_logs', ['target_id' => $entry->id, 'action' => 'Accounting entry paid']);
    }

    public function test_allowance_waits_for_threshold_reserves_first_days_and_only_pays_remaining_days_next(): void
    {
        [$plan,$trainee] = $this->training();
        $this->actingAs($this->actor());
        $url = '/accounting/allowances/'.$plan->id.'/'.$trainee->id;
        $this->attend($trainee, '2026-09-28');
        $this->post($url, ['request_key' => (string) Str::uuid(), 'cutoff' => '2026-10-10'])->assertSessionHasErrors('allowance');
        $this->attend($trainee, '2026-09-29');
        $this->attend($trainee, '2026-09-30');
        $this->post($url, ['request_key' => (string) Str::uuid(), 'cutoff' => '2026-10-10'])->assertSessionHasNoErrors();
        $first = AccountingEntry::sole();
        $this->assertSame(40050, $first->net_cents);
        $this->assertDatabaseCount('accounting_allowance_days', 2);
        $this->post($url, ['request_key' => (string) Str::uuid(), 'cutoff' => '2026-10-10'])->assertSessionHasErrors('allowance');
        $this->patch('/accounting/entries/'.$first->id, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->patch('/accounting/entries/'.$first->id, ['action' => 'pay', 'paid_at' => '2026-10-01', 'payment_method' => 'bank_transfer', 'payment_reference' => 'FIRST'])->assertSessionHasNoErrors();
        $this->post($url, ['request_key' => (string) Str::uuid(), 'cutoff' => '2026-10-10'])->assertSessionHasNoErrors();
        $this->assertSame(30050, AccountingEntry::latest('id')->first()->net_cents);
        $this->assertDatabaseCount('accounting_allowance_days', 3);
    }

    public function test_allowance_rechecks_source_and_void_releases_unpaid_reserved_days(): void
    {
        [$plan,$trainee] = $this->training();
        $this->actingAs($this->actor());
        $this->attend($trainee, '2026-09-28');
        $this->attend($trainee, '2026-09-29');
        $url = '/accounting/allowances/'.$plan->id.'/'.$trainee->id;
        $this->post($url, ['request_key' => (string) Str::uuid(), 'cutoff' => '2026-10-10'])->assertSessionHasNoErrors();
        $entry = AccountingEntry::sole();
        $phases = $plan->phases;
        $phases[0]['rate_cents'] = 25000;
        $plan->update(['phases' => $phases]);
        $this->patch('/accounting/entries/'.$entry->id, ['action' => 'approve'])->assertSessionHasErrors('entry');
        $this->patch('/accounting/entries/'.$entry->id, ['action' => 'void', 'void_reason' => 'Rate changed; rebuild draft'])->assertSessionHasNoErrors();
        $this->assertSame('void', $entry->fresh()->status);
        $this->assertDatabaseCount('accounting_allowance_days', 0);
        $this->post($url, ['request_key' => (string) Str::uuid(), 'cutoff' => '2026-10-10'])->assertSessionHasNoErrors();
        $this->assertSame(50000, AccountingEntry::latest('id')->first()->net_cents);
    }
}
