<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\AttendanceRecord;
use App\Models\CampaignSchedule;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use App\Services\AttendanceScheduleService;
use App\Services\PayrollAttendanceService;
use App\Services\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function employee(bool $night = false): User
    {
        $schedule = CampaignSchedule::create(['name' => 'Payroll shift '.Str::uuid()]);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => false, 'time_in' => $night ? '22:00' : '08:00', 'time_out' => $night ? '06:00' : '17:00', 'break_start' => $night ? '02:00' : '12:00', 'break_end' => $night ? '03:00' : '13:00']);
        }
        $user = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $user->forceFill(['campaign_schedule_id' => $schedule->id])->save();

        return $user;
    }

    private function data(User $user, array $extra = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'user_id' => $user->id, 'description' => 'Salary', 'calculation' => 'workbook', 'pay_month' => '2026-10', 'payout' => 'month_end', 'regular_rate' => '100', 'basic_rate' => '80', 'fixed_allowances' => '500', 'sss' => '200', 'philhealth' => '100', 'pagibig' => '100', 'other_deductions' => '0', 'notes' => ''], $extra);
    }

    private function clock(User $user, string $date, string $in = '08:00', string $out = '17:00'): void
    {
        $s = app(AttendanceScheduleService::class);
        $s->record($user->fresh(), $date, 'time_in', $in);
        $s->record($user->fresh(), $date, 'time_out', $out);
    }

    private function login(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'accounting', 'status' => 'active']));
    }

    public function test_manual_hours_are_ignored_and_only_employee_cutoff_attendance_is_counted(): void
    {
        $user = $this->employee();
        $this->clock($user, '2026-10-11', '07:30', '18:30');
        $this->clock($user, '2026-10-10');
        $this->clock($this->employee(), '2026-10-11');
        $data = $this->data($user, ['basic_hours' => '999', 'overtime_hours' => '999', 'gross' => '1']);
        $this->login();
        $this->post('/accounting/payroll', $data)->assertSessionHasNoErrors();
        $entry = AccountingEntry::sole();
        $this->assertSame(123600, $entry->gross_cents);
        $this->assertSame(83600, $entry->net_cents);
        $this->assertSame(480, $entry->source_snapshot['payroll']['attendance']['minutes']['basic_hours']);
        $this->assertCount(1, $entry->source_snapshot['payroll']['attendance']['rows']);
        $this->assertSame('2026-10-31', $entry->source_snapshot['payroll']['pay_date']);
        $this->post('/accounting/payroll', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounting_entries', 1);
        $this->post('/accounting/payroll', array_replace($data, ['request_key' => (string) Str::uuid()]))->assertSessionHasErrors('period_start');
        $this->getJson('/accounting/payroll-attendance?'.http_build_query($data))->assertOk()->assertJsonPath('hours.basic_hours', '8.0000');
        $this->actingAs($user)->getJson('/accounting/payroll-attendance?'.http_build_query($data))->assertForbidden();
    }

    public function test_overnight_overtime_night_differential_and_breaks(): void
    {
        $user = $this->employee(true);
        OvertimeRequest::create(['user_id' => $user->id, 'request_date' => '2026-10-25', 'request_time' => '08:00', 'status' => 'approved', 'reason' => 'Work']);
        $this->clock($user, '2026-10-25', '21:30', '08:30');
        $r = app(PayrollAttendanceService::class)->summary($user, $this->data($user));
        $this->assertSame(420, $r['minutes']['basic_hours'], json_encode($r));
        $this->assertSame(120, $r['minutes']['overtime_hours']);
        $this->assertSame(420, $r['minutes']['night_hours']);
        $this->assertSame(540, $r['rows'][0]['total_minutes']);
    }

    public function test_leave_undertime_lateness_long_break_and_incomplete_punches(): void
    {
        $u = $this->employee();
        foreach (['2026-10-11' => true, '2026-10-12' => false] as $date => $paid) {
            LeaveRequest::create(['user_id' => $u->id, 'start_date' => $date, 'end_date' => $date, 'number_of_days' => 1, 'leave_type' => 'Vacation', 'is_paid' => $paid, 'status' => 'approved', 'reason' => 'Leave']);
        }
        UndertimeRequest::create(['user_id' => $u->id, 'request_date' => '2026-10-13', 'request_time' => '16:00', 'status' => 'approved', 'reason' => 'Appointment']);
        $this->clock($u, '2026-10-13', '09:00', '17:30');
        $this->clock($u, '2026-10-14');
        $s = app(AttendanceScheduleService::class);
        $s->record($u->fresh(), '2026-10-14', 'lunch_out', '11:30');
        $s->record($u->fresh(), '2026-10-14', 'lunch_in', '13:30');
        $s->record($u->fresh(), '2026-10-15', 'time_in', '08:00');
        $r = app(PayrollAttendanceService::class)->summary($u, $this->data($u));
        $this->assertSame(1260, $r['minutes']['basic_hours']);
        $this->assertSame(0, $r['minutes']['overtime_hours']);
        $this->assertCount(1, $r['warnings']);
        $this->assertSame(480, $r['rows'][0]['leave_minutes']);
        $this->assertSame(0, $r['rows'][1]['total_minutes']);
    }

    public function test_holiday_and_rest_premiums_come_from_worked_hours_without_duplicate_rest_credit(): void
    {
        $u = $this->employee();
        $this->clock($u, '2026-10-11');
        $this->clock($u, '2026-10-12');
        $r = new AttendanceRecord(['user_id' => $u->id, 'attendance_date' => '2026-10-13', 'time_in' => '2026-10-13 00:00:00', 'time_out' => '2026-10-13 08:00:00']);
        $r->schedule_snapshot = ['name' => 'Rest', 'timezone' => 'Asia/Manila', 'rest_day' => true, 'times' => []];
        $r->save();
        $r = app(PayrollAttendanceService::class)->summary($u, $this->data($u, ['regular_holidays' => '2026-10-11', 'special_holidays' => '2026-10-12,2026-10-13']));
        $this->assertSame(480, $r['minutes']['holiday_hours']);
        $this->assertSame(960, $r['minutes']['rest_hours']);
        $this->assertSame(1440, $r['minutes']['basic_hours']);
    }

    public function test_changed_attendance_blocks_approval_and_payment_and_old_drafts_require_recreation(): void
    {
        $u = $this->employee();
        $this->clock($u, '2026-10-11');
        $this->login();
        $this->post('/accounting/payroll', $this->data($u))->assertSessionHasNoErrors();
        $entry = AccountingEntry::sole();
        $url = '/accounting/entries/'.$entry->id;
        app(AttendanceScheduleService::class)->record($u->fresh(), '2026-10-11', 'time_in', '09:00');
        $this->patch($url, ['action' => 'approve'])->assertSessionHasErrors('entry');
        app(AttendanceScheduleService::class)->record($u->fresh(), '2026-10-11', 'time_in', '08:00');
        $this->patch($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->clock($u, '2026-10-12');
        $this->patch($url, ['action' => 'pay', 'paid_at' => now()->toDateString(), 'payment_method' => 'cash', 'payment_reference' => 'TEST'])->assertSessionHasErrors('entry');
        $entry->forceFill(['status' => 'draft', 'source_snapshot' => ['payroll' => ['version' => 1]]])->save();
        $this->patch($url, ['action' => 'approve'])->assertSessionHasErrors('entry');
    }

    public function test_calendar_edges_exact_minutes_and_invalid_input(): void
    {
        $u = $this->employee();
        $this->clock($u, '2026-10-11', '08:00', '08:01');
        $r = app(PayrollCalculationService::class)->calculate($this->data($u), $u);
        $this->assertSame(133, $r['earnings'][0]['amount_cents']);
        $s = app(PayrollAttendanceService::class);
        $p = $s->period(['pay_month' => '2027-01', 'payout' => 'mid_month']);
        $this->assertSame('2026-12-26', $p['period_start']);
        $this->assertSame('2027-01-10', $p['period_end']);
        $this->assertSame('2027-01-15', $p['pay_date']);
        $this->assertSame('2028-02-29', $s->period(['pay_month' => '2028-02', 'payout' => 'month_end'])['pay_date']);
        $this->login();
        foreach ([['payout' => 'mid_month'], ['regular_rate' => '80'], ['sss' => '20000'], ['other_deductions' => '10'], ['regular_holidays' => '2026-10-01'], ['regular_holidays' => '2026-10-11', 'special_holidays' => '2026-10-11']] as $change) {
            $this->post('/accounting/payroll',$this->data($u,$change))->assertSessionHasErrors();
        }
        $this->post('/accounting/payroll',['request_key' => (string) Str::uuid(), 'user_id' => $u->id, 'description' => 'Manual', 'gross' => '100', 'deduction' => '0'])->assertSessionHasErrors('calculation');
        $this->assertDatabaseCount('accounting_entries',0);
    }
}
