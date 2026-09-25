<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AssessmentNotification;
use App\Models\AttendanceRecord;
use App\Models\CampaignSchedule;
use App\Models\EmployeeSanction;
use App\Models\EodReport;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\UndertimeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'agent'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    public function test_all_alert_categories_and_recipient_privacy(): void
    {
        $admin = $this->person('admin');
        $employee = $this->person();
        $outsider = $this->person();
        Announcement::create(['title' => 'News', 'body' => 'Company news', 'author_name' => 'Admin']);
        foreach ([OvertimeRequest::class, UndertimeRequest::class] as $model) {
            $model::create(['user_id' => $employee->id, 'request_date' => now()->toDateString(), 'request_time' => '18:00', 'reason' => 'Private reason']);
        }
        LeaveRequest::create(['user_id' => $employee->id, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'number_of_days' => 1, 'leave_type' => 'Paid', 'reason' => 'Private leave']);
        EmployeeSanction::create(['request_id' => Str::uuid(), 'employee_id' => $employee->id, 'issued_by' => $admin->id, 'employee_name' => $employee->name, 'employee_role' => 'agent', 'issuer_name' => $admin->name, 'sanction_name' => 'Tardiness', 'punishment' => 'First Written']);
        EodReport::create(['request_id' => Str::uuid(), 'user_id' => $employee->id, 'author_name' => $employee->name, 'author_role' => 'team_leader', 'report_date' => now()->toDateString(), 'summary' => 'Private EOD', 'task_snapshots' => [], 'task_count' => 0]);
        AttendanceRecord::create(['user_id' => $employee->id, 'attendance_date' => now()->subDay()->toDateString(), 'status' => 'late']);
        AttendanceRecord::create(['user_id' => $employee->id, 'attendance_date' => now()->subDays(2)->toDateString(), 'status' => 'absent']);
        $data = $this->actingAs($admin)->getJson('/notification-feed')->assertOk()->json();
        $this->assertEqualsCanonicalizing(['announcement', 'overtime', 'undertime', 'leave', 'sanction', 'eod', 'late', 'absence'], array_column($data['notifications']['data'], 'type'));
        $this->actingAs($outsider)->getJson('/notification-feed')->assertJsonPath('unread_count', 1)->assertJsonMissing(['message' => 'Private EOD']);
        $data = $this->actingAs($employee)->getJson('/notification-feed')->assertOk()->json();
        $this->assertEqualsCanonicalizing(['announcement', 'sanction', 'late', 'absence'], array_column($data['notifications']['data'], 'type'));
        $request = OvertimeRequest::first();
        $request->update(['status' => 'approved']);
        $this->getJson('/notification-feed')->assertJsonPath('unread_count', 5);
        $this->actingAs($admin)->getJson('/notification-feed')->assertJsonPath('unread_count', 7);
    }

    public function test_read_state_persists_and_other_users_cannot_mark_it_read(): void
    {
        $user = $this->person();
        $other = $this->person();
        $announcement = Announcement::create(['title' => 'News', 'body' => 'News body', 'author_name' => 'Admin']);
        $data = $this->actingAs($user)->getJson('/notification-feed')->assertJsonPath('unread_count', 1)->json();
        $id = $data['notifications']['data'][0]['id'];
        $this->patchJson("/notification-feed/$id/read")->assertOk();
        $this->getJson('/notification-feed')->assertJsonPath('unread_count', 0);
        $this->assertDatabaseCount('assessment_notifications', 1);
        $this->actingAs($other)->patchJson("/notification-feed/$id/read")->assertForbidden();
        $announcement->delete();
        $this->actingAs($user)->getJson('/notification-feed')->assertJsonPath('notifications.total', 0);
    }

    public function test_bulk_read_only_marks_displayed_owned_notifications(): void
    {
        $user = $this->person();
        $other = $this->person();
        $ids = [];
        foreach ([$user, $user, $other] as $i => $person) {
            $ids[] = AssessmentNotification::create(['recipient_id' => $person->id, 'deduplication_key' => "test:$i", 'type' => 'assigned', 'title' => 'Test', 'message' => 'Test'])->id;
        }
        $this->actingAs($user)->patchJson('/notification-feed/read', ['ids' => [$ids[0], $ids[2]]])->assertOk();
        $this->assertNotNull(AssessmentNotification::find($ids[0])->read_at);
        $this->assertNull(AssessmentNotification::find($ids[1])->read_at);
        $this->assertNull(AssessmentNotification::find($ids[2])->read_at);
    }

    public function test_absence_only_after_shift_end_and_not_on_leave_or_rest_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 10:00', 'Asia/Manila'));
        $user = $this->person();
        $user->forceFill(['created_at' => now()->subDays(5)])->save();
        $schedule = CampaignSchedule::create(['name' => 'Friday shift']);
        $schedule->days()->create(['day' => 5, 'no_schedule' => false, 'time_in' => '08:00', 'time_out' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00']);
        $user->campaign_schedule_id = $schedule->id;
        $user->save();
        $this->actingAs($user)->getJson('/notification-feed')->assertJsonPath('unread_count', 0);
        $this->travelTo(CarbonImmutable::parse('2026-09-25 18:00', 'Asia/Manila'));
        $this->getJson('/notification-feed')->assertJsonPath('unread_count', 1)->assertJsonPath('notifications.data.0.type', 'absence');
        LeaveRequest::create(['user_id' => $user->id, 'start_date' => '2026-09-25', 'end_date' => '2026-09-25', 'number_of_days' => 1, 'leave_type' => 'Paid', 'reason' => 'Leave', 'status' => 'approved']);
        $data = $this->getJson('/notification-feed')->assertOk()->json();
        $this->assertSame(['leave'], array_column($data['notifications']['data'], 'type'));
    }

    public function test_guests_and_inactive_users_are_denied(): void
    {
        $this->getJson('/notification-feed')->assertUnauthorized();
        $user = $this->person('admin');
        $user->update(['status' => 'inactive']);
        $this->actingAs($user)->getJson('/notification-feed')->assertForbidden();
    }
}
