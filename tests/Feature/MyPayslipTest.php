<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyPayslipTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $role = 'agent'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function payslip(User $employee, array $changes = []): AccountingEntry
    {
        return AccountingEntry::create([...[
            'request_key' => (string) Str::uuid(), 'user_id' => $employee->id, 'created_by' => $employee->id,
            'kind' => 'payroll', 'description' => 'October salary', 'status' => 'paid',
            'period_start' => '2026-10-11', 'period_end' => '2026-10-25', 'paid_at' => '2026-10-31',
            'gross_cents' => 1500000, 'deduction_cents' => 50000, 'net_cents' => 1450000,
            'notes' => 'SECRET-INTERNAL', 'payment_method' => 'bank_transfer', 'payment_reference' => 'PAY-TEST',
            'source_snapshot' => ['employee_name' => 'Recorded employee name', 'employee_id' => 'DVXTEST', 'internal' => 'SECRET-INTERNAL',
                'payroll' => ['pay_date' => '2026-10-31', 'inputs' => ['notes' => 'SECRET-INTERNAL'],
                    'earnings' => [['label' => 'Basic pay', 'rate' => '100.0000', 'hours' => '150', 'amount_cents' => 1500000]],
                    'deductions' => [['label' => 'SSS', 'amount_cents' => 50000]],
                    'attendance' => ['fingerprint' => 'SECRET-INTERNAL', 'rows' => [['date' => '2026-10-11', 'status' => 'present', 'worked_minutes' => 480, 'leave_minutes' => 0, 'total_minutes' => 480, 'manual_hours' => ['reason' => 'SECRET-INTERNAL']]]]]],
        ], ...$changes]);
    }

    public function test_employees_see_only_own_paid_payroll_and_safe_breakdown_fields(): void
    {
        $this->withoutVite();
        $employee = $this->employee();
        $own = $this->payslip($employee);
        $other = $this->payslip($this->employee());
        $hidden = [];
        foreach (['draft', 'approved', 'void'] as $status) {
            $hidden[] = $this->payslip($employee, ['status' => $status]);
        }
        $hidden[] = $this->payslip($employee, ['kind' => 'training_allowance']);
        $this->actingAs($employee)->get('/my-payslips?user_id='.$other->user_id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('my-payslips')->has('payslips.data', 1)->where('payslips.data.0.id', $own->id)->missing('payslips.data.0.source_snapshot')->missing('payslips.data.0.notes'));
        $response = $this->getJson('/my-payslips/'.$own->id)->assertOk()->assertJsonPath('net_cents', 1450000)->assertJsonPath('earnings.0.label', 'Basic pay')->assertJsonPath('deductions.0.label', 'SSS')->assertJsonPath('employee_name', 'Recorded employee name');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('SECRET-INTERNAL', $response->getContent());
        foreach ([$other, ...$hidden] as $entry) {
            $this->getJson('/my-payslips/'.$entry->id)->assertNotFound();
            $this->get('/my-payslips?entry='.$entry->id)->assertNotFound();
        }
        $this->get('/my-payslips?entry='.$own->id)->assertInertia(fn (Assert $page) => $page->where('selectedEntry', $own->id));
    }

    public function test_all_employee_portals_have_personal_only_access(): void
    {
        $this->withoutVite();
        $other = $this->payslip($this->employee());
        foreach (['agent', 'team_leader', 'admin', 'accounting', 'qa_admin', 'manager'] as $role) {
            $employee = $this->employee($role);
            $entry = $this->payslip($employee);
            $this->actingAs($employee)->get('/my-payslips')->assertOk();
            $this->getJson('/my-payslips/'.$entry->id)->assertOk();
            $this->getJson('/my-payslips/'.$other->id)->assertNotFound();
            $this->post('/my-payslips/'.$entry->id, ['net_cents' => 1])->assertStatus(405);
        }
    }

    public function test_empty_state_legacy_records_pagination_and_inactive_access(): void
    {
        $this->withoutVite();
        $employee = $this->employee();
        $this->actingAs($employee)->get('/my-payslips')->assertInertia(fn (Assert $page) => $page->has('payslips.data', 0));
        $legacy = $this->payslip($employee, ['source_snapshot' => null]);
        $this->getJson('/my-payslips/'.$legacy->id)->assertOk()->assertJsonPath('earnings', null)->assertJsonPath('deductions', null)->assertJsonPath('attendance', []);
        foreach (range(1, 12) as $i) {
            $this->payslip($employee);
        }
        $this->get('/my-payslips')->assertInertia(fn (Assert $page) => $page->has('payslips.data', 12)->where('payslips.last_page', 2));
        $this->get('/my-payslips?page=2')->assertInertia(fn (Assert $page) => $page->has('payslips.data', 1));
        $employee->update(['status' => 'suspended']);
        $this->get('/my-payslips')->assertRedirect('/login');
    }

    public function test_guests_cannot_read_payslips(): void
    {
        $this->get('/my-payslips')->assertRedirect('/login');
        $this->getJson('/my-payslips/1')->assertUnauthorized();
    }
}
