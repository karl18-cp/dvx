<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CampaignSchedule;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceFaceVerifier;
use App\Services\AttendanceScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PersonalAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_trainee_can_clock_with_enrolled_face_and_personal_schedule(): void
    {
        $trainee = $this->leader();
        $campaign = \App\Models\Campaign::create(['name' => 'Trainee attendance', 'abbreviation' => 'TA', 'is_active' => true]);
        $trainee->forceFill(['role' => 'trainee', 'training_status' => 'in_training', 'training_campaign_id' => $campaign->id])->save();
        $this->actingAs($trainee);
        $this->mock(AttendanceFaceVerifier::class)->shouldReceive('verify')->once()->withArgs(fn ($user) => $user->id === $trainee->id)->andReturnNull();
        $this->verify($this->challenge())->assertOk();
        $record = AttendanceRecord::sole();
        $this->assertSame($trainee->id, $record->user_id);
        $this->assertSame('08:00', $record->time_in->setTimezone('Asia/Manila')->format('H:i'));
    }

    private function leader(bool $night = false): User
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 '.($night ? '21:45:00' : '07:45:00'), 'Asia/Manila'));
        $user = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $schedule = CampaignSchedule::create(['name' => 'Clock shift']);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => false, 'time_in' => $night ? '22:00' : '08:00', 'time_out' => $night ? '07:00' : '17:00', 'break_start' => $night ? '02:00' : '12:00', 'break_end' => $night ? '03:00' : '13:00']);
        }
        $user->forceFill(['campaign_schedule_id' => $schedule->id])->save();
        $user->faceCredential()->create(['encrypted_descriptor' => array_fill(0, 1024, 0.1), 'model_version' => 'human-3.3.6-faceres', 'consented_at' => now(), 'enrolled_at' => now()]);
        $this->actingAs($user)->withCredentials();

        return $user;
    }

    private function challenge(string $action = 'time_in'): string
    {
        $id = $this->postJson('/my-attendance/challenge', ['action' => $action])->assertOk()->json('id');
        $this->withCookie(config('session.cookie'), session()->getId());

        return $id;
    }

    private function verify(string $id, array $extra = [])
    {
        return $this->postJson('/my-attendance/verify', ['challenge_id' => $id, 'frames' => array_fill(0, 3, 'data:image/jpeg;base64,YWJj'), ...$extra]);
    }

    public function test_clock_page_does_not_expose_the_enrolled_descriptor(): void
    {
        $leader = $this->leader();
        $this->get('/my-attendance')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('my-attendance')->where('clock.faceEnrolled', true)->where('clock.actions', ['time_in'])
            ->missing('clock.descriptor')->missing('clock.faceCredential'));
        $this->assertArrayNotHasKey('encrypted_descriptor', $leader->faceCredential->toArray());
    }

    public function test_records_modal_only_returns_the_signed_in_leaders_selected_day(): void
    {
        $leader = $this->leader();
        $other = User::factory()->create(['role' => 'admin']);
        app(AttendanceScheduleService::class)->record($leader, '2026-09-28', 'time_in', '07:45');
        AttendanceRecord::create(['user_id' => $other->id, 'attendance_date' => '2026-09-28', 'status' => 'absent']);
        $this->getJson('/my-attendance/records?date=2026-09-28&user_id='.$other->id)->assertOk()
            ->assertJsonPath('date', '2026-09-28')->assertJsonPath('schedule', 'Clock shift')
            ->assertJsonPath('times.0.actual', '2026-09-27T23:45:00.000000Z')
            ->assertJsonPath('times.0.credited', '2026-09-28T00:00:00.000000Z');
        $this->getJson('/my-attendance/records?date=2026-09-27')->assertOk()->assertJsonPath('times.0.actual', null);
        $this->getJson('/my-attendance/records?date=invalid')->assertUnprocessable();
        $this->assertDatabaseCount('attendance_records', 2);
        $this->actingAs($other)->getJson('/my-attendance/records?date=2026-09-28')->assertForbidden();
    }

    public function test_face_verified_punches_use_current_user_server_time_schedule_and_break_deduction(): void
    {
        $leader = $this->leader();
        $other = User::factory()->create();
        $this->mock(AttendanceFaceVerifier::class)->shouldReceive('verify')->twice()->withArgs(fn ($user, $frames, $direction) => $user->id === $leader->id && count($frames) === 3 && in_array($direction, ['left', 'right']))->andReturnNull();
        $id = $this->challenge();
        $this->verify($id, ['user_id' => $other->id, 'time' => '06:00', 'attendance_date' => '2020-01-01'])->assertOk();
        $record = AttendanceRecord::sole();
        $this->assertSame($leader->id, $record->user_id);
        $this->assertSame('07:45', $record->actual_time_in->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('08:00', $record->time_in->setTimezone('Asia/Manila')->format('H:i'));
        $this->verify($id)->assertUnprocessable();
        $this->postJson('/my-attendance/challenge', ['action' => 'time_in'])->assertUnprocessable();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00', 'Asia/Manila'));
        $this->postJson('/my-attendance/break', ['action' => 'lunch_out'])->assertOk();
        $this->postJson('/my-attendance/challenge', ['action' => 'time_out'])->assertUnprocessable();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 13:00', 'Asia/Manila'));
        $this->postJson('/my-attendance/break', ['action' => 'lunch_in'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 17:30', 'Asia/Manila'));
        $this->verify($this->challenge('time_out'))->assertOk();
        $record->refresh();
        $this->assertSame(480, $record->total_minutes);
        $this->assertSame('17:00', $record->time_out->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('17:30', $record->actual_time_out->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertNotNull($leader->faceCredential->fresh()->last_verified_at);
    }

    public function test_failed_expired_foreign_and_replayed_challenges_never_record_attendance(): void
    {
        $leader = $this->leader();
        $this->mock(AttendanceFaceVerifier::class)->shouldReceive('verify')->once()->andThrow(ValidationException::withMessages(['face' => 'Face does not match.']));
        $id = $this->challenge();
        $other = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $this->actingAs($other);
        $this->verify($id)->assertUnprocessable();
        $this->actingAs($leader);
        $this->verify($id, ['face_match' => true, 'face_liveness' => 1])->assertUnprocessable();
        $this->verify($id)->assertUnprocessable();
        $expired = $this->challenge();
        $this->travel(121)->seconds();
        $this->verify($expired)->assertUnprocessable();
        $session = $this->challenge();
        DB::table('attendance_clock_challenges')->where('id', $session)->update(['session_hash' => str_repeat('0', 64)]);
        $this->verify($session)->assertUnprocessable();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_missing_enrollment_wrong_role_invalid_sequences_and_leave_are_blocked(): void
    {
        $user = $this->leader();
        $this->postJson('/my-attendance/break', ['action' => 'time_in'])->assertUnprocessable();
        $this->postJson('/my-attendance/break', ['action' => 'lunch_out'])->assertUnprocessable();
        $user->faceCredential()->delete();
        $this->postJson('/my-attendance/challenge', ['action' => 'time_in'])->assertUnprocessable();
        LeaveRequest::create(['user_id' => $user->id, 'start_date' => '2026-09-28', 'end_date' => '2026-09-28', 'number_of_days' => 1, 'leave_type' => 'Vacation', 'reason' => 'Leave', 'status' => 'approved', 'is_paid' => true]);
        $this->getJson('/my-attendance/status')->assertJsonPath('actions', []);
        $this->postJson('/my-attendance/challenge', ['action' => 'time_in'])->assertUnprocessable();
        $user->update(['role' => 'admin']);
        $this->getJson('/my-attendance/status')->assertForbidden();
        $this->postJson('/my-attendance/challenge', ['action' => 'time_in'])->assertForbidden();
    }

    public function test_overnight_clock_out_stays_on_the_shift_start_date(): void
    {
        $this->leader(true);
        $this->mock(AttendanceFaceVerifier::class)->shouldReceive('verify')->twice()->andReturnNull();
        $this->verify($this->challenge())->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-09-29 07:15', 'Asia/Manila'));
        $this->getJson('/my-attendance/status')->assertJsonPath('date', '2026-09-28');
        $this->verify($this->challenge('time_out'))->assertOk();
        $record = AttendanceRecord::sole();
        $this->assertSame('2026-09-28', $record->attendance_date->toDateString());
        $this->assertSame(480, $record->total_minutes);
    }
}
