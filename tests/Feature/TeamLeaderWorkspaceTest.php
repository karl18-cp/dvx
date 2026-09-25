<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Campaign;
use App\Models\CampaignSchedule;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamLeaderWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function people(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'ADMIN']);
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active', 'username' => 'LEADER']);
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active', 'username' => 'AGENT']);
        $other = User::factory()->create(['role' => 'team_leader', 'status' => 'active', 'username' => 'OTHER']);
        $campaign = Campaign::create(['name' => 'Support', 'abbreviation' => 'SUP']);
        $team = Team::create(['name' => 'Team A', 'campaign_id' => $campaign->id]);
        TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $agent->id]);

        return [$admin, $leader, $agent, $other, $team];
    }

    private function apply(User $user, array $extra = []): LeaveRequest
    {
        $this->actingAs($user)->post('/leave-requests', [...$this->payload(), ...$extra])->assertRedirect('/leave-requests')->assertSessionHasNoErrors();

        return LeaveRequest::latest('id')->firstOrFail();
    }

    private function payload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'start_date' => '2026-09-28', 'end_date' => '2026-09-28', 'leave_type' => 'Vacation', 'reason' => 'Family time'];
    }

    public function test_two_stage_approval_only_credits_paid_attendance_after_admin(): void
    {
        [$admin, $leader, $agent] = $this->people();
        $schedule = CampaignSchedule::create(['name' => 'Day shift']);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => $day > 5, 'time_in' => '08:00', 'time_out' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00']);
        }
        $agent->forceFill(['campaign_schedule_id' => $schedule->id])->save();
        $leave = $this->apply($agent, ['user_id' => $leader->id, 'is_paid' => true, 'status' => 'approved', 'leader_status' => 'approved']);
        $this->assertSame($agent->id, $leave->user_id);
        $this->assertFalse($leave->isPaid());
        $this->assertSame('pending', $leave->leader_status);
        $this->actingAs($admin)->patch("/requests/leave/$leave->id/status", ['status' => 'approved', 'is_paid' => true])->assertSessionHasErrors('status');
        $this->actingAs($leader)->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'approved', 'notes' => 'Coverage arranged'])->assertSessionHasNoErrors();
        $this->assertSame('needs_review', $leave->fresh()->status);
        $this->assertDatabaseCount('attendance_records', 0);
        $this->actingAs($admin)->patch("/requests/leave/$leave->id/status", ['status' => 'approved', 'is_paid' => true])->assertSessionHasNoErrors();
        $this->assertSame('approved', $leave->fresh()->status);
        $record = AttendanceRecord::sole();
        $this->assertSame('on_leave', $record->status);
        $this->assertSame(480, $record->total_minutes);
        $this->assertSame(480, $record->leave_minutes);
    }

    public function test_team_leader_applies_directly_to_admin_and_cannot_self_approve(): void
    {
        [$admin, $leader] = $this->people();
        $leave = $this->apply($leader);
        $this->assertFalse($leave->requires_leader_approval);
        $this->assertSame('not_required', $leave->leader_status);
        $this->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'approved'])->assertForbidden();
        $this->patch("/requests/leave/$leave->id/status", ['status' => 'approved'])->assertForbidden();
        $this->actingAs($admin)->patch("/requests/leave/$leave->id/status", ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_review_permission_follows_current_team_and_rejection_needs_notes(): void
    {
        [$admin, $leader, $agent, $other, $team] = $this->people();
        $leave = $this->apply($agent);
        $this->actingAs($other)->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($agent)->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($leader)->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'rejected'])->assertSessionHasErrors('notes');
        TeamLeaderAssignment::where('team_id', $team->id)->update(['user_id' => $other->id]);
        $this->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($other)->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'rejected', 'notes' => 'Please choose another date'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertDatabaseCount('attendance_records', 0);
        $this->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'approved'])->assertSessionHasErrors('decision');
        $this->actingAs($admin)->patch("/requests/leave/$leave->id/status", ['status' => 'approved'])->assertSessionHasErrors('status');
    }

    public function test_directory_attendance_and_leave_list_are_scoped(): void
    {
        [, $leader, $agent, $other] = $this->people();
        $this->apply($agent);
        $this->apply($other);
        $this->actingAs($leader)->get('/my-team')->assertOk()->assertInertia(fn (Assert $page) => $page->component('my-team')->has('members', 1)->where('members.0.id', $agent->id)->where('summary.pendingRequests', 1));
        $this->get('/attendance')->assertOk()->assertInertia(fn (Assert $page) => $page->where('canOverride', false)->has('employees', 2));
        $this->get('/attendance?scope=mine&date=2026-09-24&user_id='.$other->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('attendanceScope', 'mine')->where('attendanceDate', '2026-09-24')->where('canOverride', false)
            ->has('employees', 1)->where('employees.0.id', $leader->id));
        $this->get('/leave-requests?scope=team')->assertOk()->assertInertia(fn (Assert $page) => $page->component('leave-requests')->has('requests.data', 1)->where('requests.data.0.employeeId', 'AGENT'));
        $this->get('/leave-requests')->assertOk()->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
        $this->get('/employees')->assertForbidden();
        $this->put(route('attendance.override-time', $agent), [])->assertForbidden();
        $this->actingAs($agent)->get('/my-team')->assertForbidden();
        $this->get('/leave-requests?scope=team')->assertForbidden();
        $leader->update(['status' => 'inactive']);
        $this->actingAs($leader)->get('/my-team')->assertForbidden();
        $this->get('/leave-requests')->assertForbidden();
    }

    public function test_duplicate_retries_overlap_and_invalid_dates(): void
    {
        [, , $agent] = $this->people();
        $payload = $this->payload();
        $this->actingAs($agent)->post('/leave-requests', $payload)->assertSessionHasNoErrors();
        $this->post('/leave-requests', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leave_requests', 1);
        $this->post('/leave-requests', $this->payload())->assertSessionHasErrors('start_date');
        $this->post('/leave-requests', [...$this->payload(), 'end_date' => '2026-09-27'])->assertSessionHasErrors('end_date');
        $this->post('/leave-requests', [...$this->payload(), 'end_date' => '2030-01-01'])->assertSessionHasErrors('end_date');
    }

    public function test_notifications_follow_the_approval_stage(): void
    {
        [$admin, $leader, $agent, $other] = $this->people();
        $leave = $this->apply($agent);
        $this->actingAs($leader)->getJson('/notification-feed')->assertJsonPath('unread_count', 1)->assertJsonPath('notifications.data.0.title', 'Agent leave awaiting your review');
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $leader->id, 'action_url' => '/leave-requests?scope=team']);
        $this->actingAs($other)->getJson('/notification-feed')->assertJsonPath('unread_count', 0);
        $this->actingAs($admin)->getJson('/notification-feed')->assertJsonPath('unread_count', 0);
        $this->actingAs($leader)->patch("/leave-requests/$leave->id/initial-review", ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->getJson('/notification-feed')->assertJsonPath('unread_count', 0);
        $this->actingAs($admin)->getJson('/notification-feed')->assertJsonPath('unread_count', 1);
        $this->actingAs($agent)->getJson('/notification-feed')->assertJsonPath('unread_count', 1)->assertJsonPath('notifications.data.0.title', 'Leave forwarded for final admin approval');
    }
}
