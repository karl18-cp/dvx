<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CampaignSchedule;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function employee(bool $night = false): User
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $schedule = CampaignSchedule::create(['name' => 'Approved shift']);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => $day > 5, 'time_in' => $night ? '22:00' : '08:00', 'time_out' => $night ? '06:00' : '17:00', 'break_start' => $night ? '02:00' : '12:00', 'break_end' => $night ? '03:00' : '13:00']);
        }
        $user = User::factory()->create(['username' => 'APPROVAL001']);
        $user->campaign_schedule_id = $schedule->id;
        $user->save();

        return $user;
    }

    private function clock(User $employee, string $field, string $time): void
    {
        $this->put(route('attendance.override-time', $employee), ['attendance_date' => '2026-09-21', 'field' => $field, 'time' => $time])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function decision(string $type, $request, string $status = 'approved', array $extra = [])
    {
        return $this->patch(route('requests.update-status', ['type' => $type, 'requestId' => $request->id]), ['status' => $status, ...$extra])->assertRedirect();
    }

    private function leave(User $user, string $type = 'Paid', string $end = '2026-09-21'): LeaveRequest
    {
        return LeaveRequest::create(['user_id' => $user->id, 'start_date' => '2026-09-21', 'end_date' => $end, 'number_of_days' => 1, 'leave_type' => $type, 'reason' => 'Test leave']);
    }

    public function test_overtime_approval_recalculates_existing_punches_and_revocation_removes_extra_hours(): void
    {
        $user = $this->employee();
        $request = OvertimeRequest::create(['user_id' => $user->id, 'request_date' => '2026-09-21', 'request_time' => '19:00', 'reason' => 'Finish work']);
        $this->clock($user, 'time_in', '07:45');
        $this->clock($user, 'time_out', '19:30');
        $this->assertSame(480, AttendanceRecord::sole()->total_minutes);
        $this->decision('overtime', $request)->assertSessionHasNoErrors();
        $record = AttendanceRecord::sole();
        $this->assertSame(600, $record->total_minutes);
        $this->assertSame('19:00', $record->time_out->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('19:30', $record->actual_time_out->setTimezone('Asia/Manila')->format('H:i'));
        $this->decision('overtime', $request, 'rejected')->assertSessionHasNoErrors();
        $this->assertSame(480, AttendanceRecord::sole()->total_minutes);
    }

    public function test_approved_overtime_does_not_credit_unworked_time(): void
    {
        $user = $this->employee();
        $request = OvertimeRequest::create(['user_id' => $user->id, 'request_date' => '2026-09-21', 'request_time' => '19:00', 'reason' => 'Finish work']);
        $this->decision('overtime', $request)->assertSessionHasNoErrors();
        $this->assertNull(AttendanceRecord::sole()->total_minutes);
        $this->clock($user, 'time_in', '08:00');
        $this->clock($user, 'time_out', '18:00');
        $this->assertSame(540, AttendanceRecord::sole()->total_minutes);
    }

    public function test_undertime_shortens_shift_and_deducts_only_break_overlap(): void
    {
        $user = $this->employee();
        $request = UndertimeRequest::create(['user_id' => $user->id, 'request_date' => '2026-09-21', 'request_time' => '12:30', 'reason' => 'Appointment']);
        $this->clock($user, 'time_in', '08:00');
        $this->clock($user, 'time_out', '17:00');
        $this->decision('undertime', $request)->assertSessionHasNoErrors();
        $this->assertSame(240, AttendanceRecord::sole()->total_minutes);
        $this->assertSame('12:30', AttendanceRecord::sole()->time_out->setTimezone('Asia/Manila')->format('H:i'));
    }

    public function test_paid_leave_creates_attendance_and_credits_net_scheduled_hours_only(): void
    {
        $user = $this->employee();
        $leave = $this->leave($user, 'Vacation', '2026-09-27');
        $this->decision('leave', $leave, 'approved', ['is_paid' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_records', 7);
        $records = AttendanceRecord::orderBy('attendance_date')->get();
        foreach ($records as $index => $record) {
            $this->assertSame('on_leave', $record->status);
            $this->assertSame($index < 5 ? 480 : 0, $record->total_minutes);
            $this->assertSame(0, $record->worked_minutes);
            $this->assertNull($record->time_in);
        }
        $this->get(route('attendance', ['date' => '2026-09-21']))->assertInertia(fn (Assert $page) => $page->where('employees.0.status', 'on_leave')->where('employees.0.totalMinutes', 480)->where('employees.0.approvals.leave.paid', true));
        $user->campaignSchedule->days()->update(['time_out' => '19:00']);
        $this->get(route('attendance', ['date' => '2026-09-21']))->assertInertia(fn (Assert $page) => $page->where('employees.0.totalMinutes', 480));
    }

    public function test_unpaid_leave_has_zero_hours_and_pending_leave_does_not_apply(): void
    {
        $user = $this->employee();
        $leave = $this->leave($user, 'Unpaid');
        $this->get(route('attendance', ['date' => '2026-09-21']))->assertInertia(fn (Assert $page) => $page->where('employees.0.status', 'not_recorded')->where('employees.0.totalMinutes', null));
        $this->decision('leave', $leave)->assertSessionHasNoErrors();
        $this->assertSame('on_leave', AttendanceRecord::sole()->status);
        $this->assertSame(0, AttendanceRecord::sole()->total_minutes);
        $this->decision('leave', $leave, 'rejected')->assertSessionHasNoErrors();
        $this->assertSame('not_recorded', AttendanceRecord::sole()->status);
        $this->assertNull(AttendanceRecord::sole()->total_minutes);
    }

    public function test_night_shift_uses_next_day_overtime_and_break_deduction(): void
    {
        $user = $this->employee(true);
        $request = OvertimeRequest::create(['user_id' => $user->id, 'request_date' => '2026-09-21', 'request_time' => '08:00', 'reason' => 'Night overtime']);
        $this->decision('overtime', $request)->assertSessionHasNoErrors();
        $this->clock($user, 'time_in', '21:50');
        $this->clock($user, 'lunch_out', '01:45');
        $this->clock($user, 'lunch_in', '03:15');
        $this->clock($user, 'time_out', '08:30');
        $this->assertSame(510, AttendanceRecord::sole()->total_minutes);
        $this->assertSame('2026-09-22 08:00', AttendanceRecord::sole()->time_out->setTimezone('Asia/Manila')->format('Y-m-d H:i'));
    }

    public function test_conflicting_approvals_are_rejected_and_paid_leave_is_not_double_counted(): void
    {
        $user = $this->employee();
        $overtime = OvertimeRequest::create(['user_id' => $user->id, 'request_date' => '2026-09-21', 'request_time' => '19:00', 'reason' => 'OT']);
        $undertime = UndertimeRequest::create(['user_id' => $user->id, 'request_date' => '2026-09-21', 'request_time' => '16:00', 'reason' => 'UT']);
        $this->decision('overtime', $overtime)->assertSessionHasNoErrors();
        $this->decision('undertime', $undertime)->assertSessionHasErrors('status');
        $this->clock($user, 'time_in', '08:00');
        $this->clock($user, 'time_out', '19:00');
        $leave = $this->leave($user);
        $this->decision('leave', $leave)->assertSessionHasNoErrors();
        $this->assertSame(480, AttendanceRecord::sole()->total_minutes);
        $this->assertSame(0, AttendanceRecord::sole()->worked_minutes);
        $overlap = $this->leave($user);
        $this->decision('leave', $overlap)->assertSessionHasErrors('status');
    }

    public function test_leave_without_an_assigned_schedule_cannot_invent_hours(): void
    {
        $user = $this->employee();
        $user->campaign_schedule_id = null;
        $user->save();
        $this->decision('leave', $this->leave($user))->assertSessionHasNoErrors();
        $this->assertSame(0, AttendanceRecord::sole()->total_minutes);
    }
}
