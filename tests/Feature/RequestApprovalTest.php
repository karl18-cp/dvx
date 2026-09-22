<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_tables_are_linked_to_the_employee_user(): void
    {
        $employee = User::factory()->create(['username' => 'DVX002', 'role' => 'agent']);

        $undertime = $employee->undertimeRequests()->create([
            'request_date' => '2026-08-04', 'request_time' => '16:00',
            'reason' => 'Appointment',
        ]);
        $overtime = $employee->overtimeRequests()->create([
            'request_date' => '2026-08-04', 'request_time' => '19:00',
            'reason' => 'Finish campaign work',
        ]);
        $leave = $employee->leaveRequests()->create([
            'start_date' => '2026-08-10', 'end_date' => '2026-08-11',
            'number_of_days' => 2, 'leave_type' => 'Paid', 'reason' => 'Family event',
        ]);

        $this->assertTrue($undertime->user->is($employee));
        $this->assertTrue($overtime->user->is($employee));
        $this->assertTrue($leave->user->is($employee));

        $employee->delete();

        $this->assertDatabaseCount('undertime_requests', 0);
        $this->assertDatabaseCount('overtime_requests', 0);
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_admin_can_view_and_approve_employee_requests(): void
    {
        $admin = User::factory()->create(['username' => 'DVX001', 'role' => 'admin']);
        $employee = User::factory()->create(['username' => 'DVX002', 'role' => 'agent']);
        $leave = LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'number_of_days' => 1, 'leave_type' => 'Paid', 'reason' => 'Family event',
        ]);

        $this->actingAs($admin)->get(route('requests'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests')
                ->where('leaveRequests.0.employeeId', 'DVX002')
                ->where('leaveRequests.0.status', 'needs_review'));

        $this->actingAs($admin)
            ->patch(route('requests.update-status', ['type' => 'leave', 'requestId' => $leave->id]), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('requests'));

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($admin->id, $leave->reviewed_by);
        $this->assertNotNull($leave->reviewed_at);
    }

    public function test_non_admin_cannot_access_or_approve_requests(): void
    {
        $employee = User::factory()->create(['username' => 'DVX002', 'role' => 'agent']);
        $overtime = OvertimeRequest::query()->create([
            'user_id' => $employee->id,
            'request_date' => '2026-08-04', 'request_time' => '19:00',
            'reason' => 'Work requirement',
        ]);

        $this->actingAs($employee)->get(route('requests'))->assertForbidden();
        $this->actingAs($employee)
            ->patch(route('requests.update-status', ['type' => 'overtime', 'requestId' => $overtime->id]), [
                'status' => 'approved',
            ])
            ->assertForbidden();
        $this->assertSame('needs_review', $overtime->fresh()->status);
    }
}
