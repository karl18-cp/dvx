<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_database_backed_attendance(): void
    {
        $admin = User::factory()->create([
            'username' => 'DVX001',
            'role' => 'admin',
        ]);

        AttendanceRecord::query()->create([
            'user_id' => $admin->id,
            'attendance_date' => '2026-08-04',
            'status' => 'present',
            'time_in' => '2026-08-04 08:00:00',
            'time_out' => '2026-08-04 17:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('attendance', ['date' => '2026-08-04']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance')
                ->where('attendanceDate', '2026-08-04')
                ->where('employees.0.employeeId', 'DVX001')
                ->where('employees.0.status', 'present'));
    }

    public function test_attendance_is_foreign_keyed_and_deleted_with_the_user(): void
    {
        $user = User::factory()->create(['username' => 'DVX002']);
        $record = $user->attendanceRecords()->create([
            'attendance_date' => '2026-08-04',
            'status' => 'present',
        ]);

        $this->assertTrue($record->user->is($user));

        $user->delete();

        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }

    public function test_non_admin_cannot_open_admin_attendance(): void
    {
        $employee = User::factory()->create([
            'username' => 'DVX002',
            'role' => 'agent',
        ]);

        $this->actingAs($employee)->get(route('attendance'))->assertForbidden();
    }

    public function test_admin_can_override_and_clear_employee_attendance_times(): void
    {
        $admin = User::factory()->create([
            'username' => 'DVX001',
            'role' => 'admin',
        ]);
        $employee = User::factory()->create([
            'username' => 'DVX002',
            'role' => 'agent',
        ]);

        $this->actingAs($admin)
            ->put(route('attendance.override-time', $employee), [
                'attendance_date' => '2026-08-04',
                'field' => 'time_in',
                'time' => '08:15',
            ])
            ->assertRedirect(route('attendance', ['date' => '2026-08-04']));

        $record = AttendanceRecord::query()
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', '2026-08-04')
            ->firstOrFail();

        $this->assertSame('present', $record->status);
        $this->assertSame('08:15', $record->time_in->setTimezone('Asia/Manila')->format('H:i'));

        $this->actingAs($admin)
            ->put(route('attendance.override-time', $employee), [
                'attendance_date' => '2026-08-04',
                'field' => 'time_in',
                'time' => null,
            ])
            ->assertRedirect(route('attendance', ['date' => '2026-08-04']));

        $this->assertNull($record->fresh()->time_in);
    }

    public function test_non_admin_cannot_override_attendance_times(): void
    {
        $employee = User::factory()->create([
            'username' => 'DVX002',
            'role' => 'agent',
        ]);

        $this->actingAs($employee)
            ->put(route('attendance.override-time', $employee), [
                'attendance_date' => '2026-08-04',
                'field' => 'time_in',
                'time' => '08:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attendance_records', 0);
    }
}
