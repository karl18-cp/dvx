<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CampaignSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function employee(bool $night = false, bool $rest = false): User
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $schedule = CampaignSchedule::create(['name' => 'Test shift']);
        $schedule->days()->create(['day' => 1, 'no_schedule' => $rest, 'time_in' => $night ? '22:00' : '08:00', 'time_out' => $night ? '06:00' : '17:00', 'break_start' => $night ? '02:00' : '12:00', 'break_end' => $night ? '03:00' : '13:00']);
        $employee = User::factory()->create(['username' => 'SCHEDULED']);
        $employee->campaign_schedule_id = $schedule->id;
        $employee->save();

        return $employee;
    }

    private function entry(User $employee, string $field, ?string $time, ?string $date = null)
    {
        return $this->put(route('attendance.override-time', $employee), ['attendance_date' => '2026-09-21', 'field' => $field, 'time' => $time, 'time_date' => $date])->assertStatus(302);
    }

    private function local(AttendanceRecord $record, string $field): ?string
    {
        return $record->$field?->setTimezone('Asia/Manila')->format('Y-m-d H:i');
    }

    public function test_early_arrival_short_break_and_late_departure_use_schedule_and_preserve_actual_times(): void
    {
        $employee = $this->employee();
        foreach (['time_in' => '07:40', 'lunch_out' => '12:10', 'lunch_in' => '12:50', 'time_out' => '17:30'] as $field => $time) {
            $this->entry($employee, $field, $time)->assertSessionHasNoErrors();
        }
        $record = AttendanceRecord::sole();
        foreach (['time_in' => '08:00', 'lunch_out' => '12:00', 'lunch_in' => '13:00', 'time_out' => '17:00', 'actual_time_in' => '07:40', 'actual_time_out' => '17:30'] as $field => $time) {
            $this->assertSame('2026-09-21 '.$time, $this->local($record, $field));
        }
        $this->assertSame('present', $record->status);
        $this->assertSame(8.0, $record->time_in->diffInHours($record->time_out) - $record->lunch_out->diffInHours($record->lunch_in));
    }

    public function test_late_arrival_long_break_and_early_departure_are_not_hidden(): void
    {
        $employee = $this->employee();
        foreach (['time_in' => '08:15', 'lunch_out' => '11:50', 'lunch_in' => '13:10', 'time_out' => '16:30'] as $field => $time) {
            $this->entry($employee, $field, $time)->assertSessionHasNoErrors();
            $this->assertSame('2026-09-21 '.$time, $this->local(AttendanceRecord::sole(), $field));
        }
        $this->assertSame('late', AttendanceRecord::sole()->status);
        $this->entry($employee, 'time_in', null)->assertSessionHasNoErrors();
        $this->assertNull(AttendanceRecord::sole()->actual_time_in);
        $this->assertSame('not_recorded', AttendanceRecord::sole()->status);
    }

    public function test_overnight_events_are_recorded_on_the_correct_day(): void
    {
        $employee = $this->employee(true);
        foreach (['time_in' => '21:30', 'lunch_out' => '02:10', 'lunch_in' => '02:50', 'time_out' => '06:30'] as $field => $time) {
            $this->entry($employee, $field, $time)->assertSessionHasNoErrors();
        }
        $record = AttendanceRecord::sole();
        $this->assertSame('2026-09-21 22:00', $this->local($record, 'time_in'));
        $this->assertSame('2026-09-22 02:00', $this->local($record, 'lunch_out'));
        $this->assertSame('2026-09-22 03:00', $this->local($record, 'lunch_in'));
        $this->assertSame('2026-09-22 06:00', $this->local($record, 'time_out'));
    }

    public function test_midnight_arrival_can_use_an_explicit_previous_date(): void
    {
        $employee = $this->employee();
        $employee->campaignSchedule->days()->update(['time_in' => '00:00', 'time_out' => '09:00', 'break_start' => '04:00', 'break_end' => '05:00']);
        $this->entry($employee, 'time_in', '23:45', '2026-09-20')->assertSessionHasNoErrors();
        $this->assertSame('2026-09-21 00:00', $this->local(AttendanceRecord::sole(), 'time_in'));
        $this->assertSame('2026-09-20 23:45', $this->local(AttendanceRecord::sole(), 'actual_time_in'));
    }

    public function test_schedule_changes_do_not_rewrite_the_shift_already_started(): void
    {
        $employee = $this->employee();
        $this->entry($employee, 'time_in', '07:50')->assertSessionHasNoErrors();
        $employee->campaignSchedule->delete();
        $this->entry($employee, 'time_out', '18:00')->assertSessionHasNoErrors();
        $this->assertSame('2026-09-21 17:00', $this->local(AttendanceRecord::sole(), 'time_out'));
        $this->assertSame('Test shift', AttendanceRecord::sole()->schedule_snapshot['name']);
    }

    public function test_rest_day_and_unassigned_employees_keep_actual_times(): void
    {
        $employee = $this->employee(false, true);
        $this->entry($employee, 'time_in', '07:30')->assertSessionHasNoErrors();
        $this->assertSame('2026-09-21 07:30', $this->local(AttendanceRecord::sole(), 'time_in'));
        $other = User::factory()->create();
        $this->entry($other, 'time_in', '09:15')->assertSessionHasNoErrors();
        $this->assertSame('2026-09-21 09:15', $this->local($other->attendanceRecords()->first(), 'time_in'));
    }

    public function test_invalid_event_order_is_rejected_without_changing_saved_times(): void
    {
        $employee = $this->employee();
        $this->entry($employee, 'time_in', '08:15')->assertSessionHasNoErrors();
        $this->entry($employee, 'time_out', '07:00', '2026-09-21')->assertSessionHasErrors('time');
        $this->assertNull(AttendanceRecord::sole()->time_out);
        $this->assertSame('late', AttendanceRecord::sole()->status);
        $this->entry($employee, 'lunch_out', '13:30', '2026-09-21')->assertSessionHasNoErrors();
        $this->entry($employee, 'lunch_in', '13:10', '2026-09-21')->assertSessionHasErrors('time');
        $this->assertNull(AttendanceRecord::sole()->lunch_in);
    }
}
