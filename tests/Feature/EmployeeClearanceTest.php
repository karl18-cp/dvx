<?php

namespace Tests\Feature;

use App\Models\EmployeeBackpay;
use App\Models\EmployeeClearance;
use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeClearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 9)->setTime(9, 0));
    }

    public function test_admin_and_accounting_can_view_separated_employees_but_agents_cannot(): void
    {
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'resigned', 'username' => 'DVX501']);
        User::factory()->create(['role' => 'agent', 'status' => 'active', 'username' => 'DVX502']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($admin)->get('/offboarding')->assertOk()->assertInertia(fn ($page) => $page
            ->component('employee-clearance')->where('rows.0.employee.id', $employee->id)->where('summary.employees', 1)->where('canApprove', true));
        $this->actingAs($accounting)->get('/offboarding')->assertOk()->assertInertia(fn ($page) => $page->where('canApprove', false));
        $this->actingAs($agent)->get('/offboarding')->assertForbidden();
    }

    public function test_complete_clearance_workflow_links_records_and_locks_after_admin_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'terminated', 'username' => 'DVX503']);
        EmployeeBackpay::create([
            'request_key' => Str::uuid(), 'user_id' => $employee->id, 'separation_date' => '2026-11-01',
            'separation_status' => 'terminated', 'calendar_year' => 2026, 'eligible_basic_cents' => 100000,
            'thirteenth_month_cents' => 10000, 'last_payroll_cents' => 90000, 'additions_cents' => 0,
            'deductions_cents' => 5000, 'total_cents' => 95000, 'status' => 'ready', 'source_snapshot' => [],
        ]);
        EmployeeContract::create([
            'request_key' => Str::uuid(), 'user_id' => $employee->id, 'contract_type' => 'quitclaim',
            'title' => 'Signed quitclaim', 'effective_date' => '2026-11-01', 'status' => 'active',
            'file_disk' => 'local', 'file_path' => 'test/quitclaim.pdf', 'original_filename' => 'quitclaim.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 100,
        ]);

        $this->actingAs($admin)->post('/offboarding', ['user_id' => $employee->id, 'separation_date' => '2026-11-01'])->assertSessionHasNoErrors();
        $clearance = EmployeeClearance::sole();
        $this->patch("/offboarding/{$clearance->id}/steps/property", ['status' => 'returned', 'items' => [['name' => 'Laptop', 'status' => 'returned', 'notes' => 'Good']], 'notes' => 'All returned.'])->assertSessionHasNoErrors();
        $this->patch("/offboarding/{$clearance->id}/steps/attendance", ['status' => 'verified', 'notes' => 'No final shift was scheduled.'])->assertSessionHasNoErrors();
        $this->patch("/offboarding/{$clearance->id}/steps/outstanding", ['status' => 'cleared', 'amount' => '50.00', 'notes' => 'Deducted from backpay.'])->assertSessionHasNoErrors();
        $this->patch("/offboarding/{$clearance->id}/steps/quitclaim", ['status' => 'signed', 'notes' => 'Signed document uploaded.'])->assertSessionHasNoErrors();
        $this->patch("/offboarding/{$clearance->id}/steps/interview", ['status' => 'completed', 'interview_at' => '2026-11-08 10:00', 'notes' => 'Exit interview completed.'])->assertSessionHasNoErrors();

        $this->assertSame('ready', $clearance->fresh()->status);
        $this->post("/offboarding/{$clearance->id}/approve", ['approval_notes' => 'All records were reviewed and cleared.'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $clearance->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['category' => 'offboarding', 'subject_id' => $employee->id, 'action' => 'Final employee clearance approved']);

        $this->patch("/offboarding/{$clearance->id}/steps/property", ['status' => 'pending'])->assertSessionHasErrors('clearance');
    }

    public function test_final_approval_is_rejected_until_every_gate_is_complete(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'resigned']);
        $clearance = EmployeeClearance::create(['user_id' => $employee->id, 'separation_date' => '2026-11-01', 'separation_type' => 'resigned']);

        $this->actingAs($admin)->post("/offboarding/{$clearance->id}/approve", ['approval_notes' => 'Attempting approval before completion.'])->assertSessionHasErrors('clearance');
        $this->assertSame('in_progress', $clearance->fresh()->status);
    }

    public function test_accounting_cannot_grant_final_approval(): void
    {
        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'resigned']);
        $clearance = EmployeeClearance::create(['user_id' => $employee->id, 'separation_date' => '2026-11-01', 'separation_type' => 'resigned']);

        $this->actingAs($accounting)->post("/offboarding/{$clearance->id}/approve", ['approval_notes' => 'Accounting approval attempt.'])->assertForbidden();
    }
}
