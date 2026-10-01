<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\PayrollAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManualAttendanceHoursTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'username' => fake()->unique()->bothify('USER#####')]);
    }

    private function payload(array $extra = []): array
    {
        return [...['attendance_date' => '2026-10-11', 'clear' => false, 'total_hours' => '8.5', 'overtime_hours' => '1.5', 'night_hours' => '2', 'reason' => 'Corrected from supervisor log'], ...$extra];
    }

    public function test_admin_and_accounting_can_correct_all_roles_and_payroll_uses_manual_minutes(): void
    {
        $this->withoutVite();
        foreach (['admin', 'accounting'] as $role) {
            $actor = $this->actor($role);
            foreach (['agent', 'team_leader', 'admin', 'accounting', 'qa_admin', 'trainee'] as $targetRole) {
                $employee = $this->actor($targetRole);
                $this->actingAs($actor)->put('/attendance/'.$employee->id.'/hours', $this->payload())->assertSessionHasNoErrors();
                $record = AttendanceRecord::where('user_id', $employee->id)->sole();
                $this->assertSame(510, $record->total_minutes);
                $this->assertSame($actor->id, $record->manual_hours['actor_id']);
                $summary = app(PayrollAttendanceService::class)->summary($employee, ['pay_month' => '2026-10', 'payout' => 'month_end']);
                $this->assertSame(420, $summary['minutes']['basic_hours']);
                $this->assertSame(90, $summary['minutes']['overtime_hours']);
                $this->assertSame(120, $summary['minutes']['night_hours']);
                $this->put('/attendance/'.$employee->id.'/time', ['attendance_date' => '2026-10-11', 'field' => 'time_in', 'time' => '08:00'])->assertSessionHasNoErrors();
                $this->assertNull($record->fresh()->manual_hours);
                $this->assertNull($record->fresh()->total_minutes);
            }
            $this->get('/attendance')->assertOk();
        }
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Attendance manual hours corrected']);
    }

    public function test_team_leaders_are_limited_to_members_of_all_their_teams(): void
    {
        $this->withoutVite();
        $leader = $this->actor('team_leader');
        $campaign = Campaign::create(['name' => 'Support', 'abbreviation' => 'SUP']);
        $members = [];
        foreach (['A', 'B'] as $name) {
            $team = Team::create(['name' => $name, 'campaign_id' => $campaign->id]);
            TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
            $member = $this->actor('agent');
            TeamMember::create(['team_id' => $team->id, 'user_id' => $member->id]);
            $members[] = $member;
            $this->actingAs($leader)->put('/attendance/'.$member->id.'/hours', $this->payload())->assertSessionHasNoErrors();
        }
        foreach ([$leader, $this->actor('agent'), $this->actor('admin'), $this->actor('team_leader')] as $other) {
            $this->put('/attendance/'.$other->id.'/hours', $this->payload())->assertForbidden();
            $this->put('/attendance/'.$other->id.'/time', ['attendance_date' => '2026-10-11', 'field' => 'time_in', 'time' => '08:00'])->assertForbidden();
        }
        $this->get('/attendance')->assertInertia(fn (Assert $page) => $page->has('employees', 3));
        $this->actingAs($members[0])->put('/attendance/'.$members[0]->id.'/hours', $this->payload())->assertForbidden();
    }

    public function test_invalid_totals_and_missing_reason_are_rejected_and_restore_is_audited(): void
    {
        $this->withoutVite();
        $accountant = $this->actor('accounting');
        $employee = $this->actor('agent');
        $url = '/attendance/'.$employee->id.'/hours';
        $this->actingAs($accountant);
        foreach ([['total_hours' => '-1'], ['total_hours' => '25'], ['overtime_hours' => '9'], ['night_hours' => '9'], ['reason' => '']] as $change) {
            $this->put($url, $this->payload($change))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('attendance_records', 0);
        $this->put($url, $this->payload())->assertSessionHasNoErrors();
        $this->put($url, $this->payload(['clear' => true]))->assertSessionHasNoErrors();
        $this->assertNull(AttendanceRecord::sole()->manual_hours);
        $this->assertNull(AttendanceRecord::sole()->total_minutes);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Attendance manual hours removed']);
        $accountant->update(['status' => 'suspended']);
        $this->put($url, $this->payload())->assertRedirect('/login');
    }
}
