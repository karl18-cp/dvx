<?php

namespace Tests\Feature;

use App\Models\CampaignSchedule;
use App\Models\LeaveCreditTransaction;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 19)->setTime(9, 0));
    }

    private function employee(string $startDate, string $role = 'agent'): User
    {
        $schedule = CampaignSchedule::create(['name' => 'Leave credit schedule '.fake()->unique()->numerify('###')]);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => false, 'time_in' => '08:00', 'time_out' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00']);
        }
        $employee = User::factory()->create(['role' => $role, 'status' => 'active', 'username' => fake()->unique()->bothify('DVX###'), 'campaign_schedule_id' => $schedule->id]);
        $employee->personalInformation()->create([
            'email' => $employee->email,
            'birth_date' => '1995-01-01',
            'start_date' => $startDate,
            'gender' => 'Other',
            'civil_status' => 'Single',
            'phone' => '09123456789',
            'address' => 'Test address',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_relationship' => 'Parent',
            'emergency_contact_phone' => '09123456788',
        ]);

        return $employee;
    }

    public function test_credit_is_automatic_after_one_year_and_paid_leave_is_deducted(): void
    {
        $employee = $this->employee('2025-10-19');
        LeaveRequest::create([
            'user_id' => $employee->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-20',
            'number_of_days' => 2, 'leave_type' => 'Vacation', 'reason' => 'Rest', 'status' => 'approved', 'is_paid' => true,
        ]);

        $this->actingAs($employee)->get('/leave-credits')->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave-credits')
            ->where('canViewAll', false)
            ->where('records.data.0.eligible', true)
            ->where('records.data.0.annual_credits', 5)
            ->where('records.data.0.used_credits', 2)
            ->where('records.data.0.available_credits', 3)
            ->where('records.data.0.cycle_start', '2026-10-19'));
    }

    public function test_employee_before_first_anniversary_is_not_yet_eligible(): void
    {
        $employee = $this->employee('2026-01-01');

        $this->actingAs($employee)->get('/leave-credits')->assertOk()->assertInertia(fn ($page) => $page
            ->where('records.data.0.eligible', false)
            ->where('records.data.0.available_credits', 0)
            ->where('records.data.0.eligibility_date', '2027-01-01'));
    }

    public function test_admin_cannot_approve_paid_leave_without_available_credits(): void
    {
        $employee = $this->employee('2025-01-01');
        LeaveRequest::create([
            'user_id' => $employee->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-05',
            'number_of_days' => 5, 'leave_type' => 'Vacation', 'reason' => 'Used credits', 'status' => 'approved', 'is_paid' => true,
        ]);
        $request = LeaveRequest::create([
            'user_id' => $employee->id, 'start_date' => '2026-10-21', 'end_date' => '2026-10-21',
            'number_of_days' => 1, 'leave_type' => 'Emergency', 'reason' => 'Additional day', 'status' => 'needs_review',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->patch('/requests/leave/'.$request->id.'/status', ['status' => 'approved', 'is_paid' => true])
            ->assertSessionHasErrors('status');
        $this->assertSame('needs_review', $request->fresh()->status);

        $this->patch('/requests/leave/'.$request->id.'/status', ['status' => 'approved', 'is_paid' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($request->fresh()->is_paid);
    }

    public function test_accounting_and_admin_see_all_but_agent_sees_only_self(): void
    {
        $first = $this->employee('2025-01-01');
        $second = $this->employee('2026-01-01', 'team_leader');
        $accountant = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($accountant)->get('/leave-credits')->assertOk()->assertInertia(fn ($page) => $page->where('canViewAll', true)->where('records.total', 2));
        $this->actingAs($admin)->get('/leave-credits')->assertOk()->assertInertia(fn ($page) => $page->where('records.total', 2));
        $this->actingAs($first)->get('/leave-credits')->assertOk()->assertInertia(fn ($page) => $page->where('records.total', 1)->where('records.data.0.id', $first->id));
        $this->actingAs(User::factory()->create(['role' => 'trainee', 'status' => 'active', 'training_status' => 'in_training']))->get('/leave-credits')->assertForbidden();
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_ledger_backfills_earned_used_and_expired_entries_without_duplicates(): void
    {
        $employee = $this->employee('2024-10-19');
        LeaveRequest::create([
            'user_id' => $employee->id, 'start_date' => '2025-10-20', 'end_date' => '2025-10-21',
            'number_of_days' => 2, 'leave_type' => 'Vacation', 'reason' => 'Rest', 'status' => 'approved', 'is_paid' => true,
        ]);

        $this->actingAs($employee)->get('/leave-credits?employee='.$employee->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('ledger.balance', 5)
            ->where('ledger.totals.earned', 10)
            ->where('ledger.totals.used', -2)
            ->where('ledger.totals.expired', -3));
        $this->get('/leave-credits?employee='.$employee->id)->assertOk();

        $this->assertSame(4, LeaveCreditTransaction::where('user_id', $employee->id)->count());
    }

    public function test_accounting_can_post_documented_manual_ledger_entries(): void
    {
        $employee = $this->employee('2025-01-01');
        $accountant = User::factory()->create(['role' => 'accounting', 'status' => 'active']);

        $this->actingAs($accountant)->post('/leave-credits/'.$employee->id.'/transactions', [
            'type' => 'adjustment', 'amount' => 1.5, 'effective_date' => '2026-10-19',
            'reference' => 'HR memo 100', 'notes' => 'Approved credit correction.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_credit_transactions', [
            'user_id' => $employee->id, 'created_by' => $accountant->id, 'type' => 'adjustment',
            'amount' => 1.5, 'reference' => 'HR memo 100', 'automatic' => false,
        ]);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $accountant->id, 'subject_id' => $employee->id, 'action' => 'Leave credit Adjustment']);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $employee->id, 'type' => 'leave_credit']);
    }

    public function test_agents_cannot_post_manual_ledger_entries(): void
    {
        $employee = $this->employee('2025-01-01');

        $this->actingAs($employee)->post('/leave-credits/'.$employee->id.'/transactions', [
            'type' => 'adjustment', 'amount' => 1, 'effective_date' => '2026-10-19', 'notes' => 'Unauthorized.',
        ])->assertForbidden();
    }
}
