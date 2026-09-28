<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TraineeEmployeeAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function trainee(): User
    {
        $user = User::factory()->create(['username' => 'DVXTR001', 'role' => 'trainee', 'status' => 'active', 'password' => 'training-password']);
        $user->forceFill(['training_status' => 'in_training'])->save();

        return $user;
    }

    public function test_status_scope_limits_team_leaders_and_qa_and_blocks_inactive_sessions(): void
    {
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $campaign = Campaign::create(['name' => 'Scope', 'abbreviation' => 'S']);
        $team = Team::create(['name' => 'Own team', 'campaign_id' => $campaign->id]);
        TeamLeaderAssignment::create(['user_id' => $leader->id, 'team_id' => $team->id]);
        TeamMember::create(['user_id' => $agent->id, 'team_id' => $team->id]);
        $this->actingAs($leader)->get('/account-statuses')->assertInertia(fn (Assert $p) => $p->has('employees', 1)->where('employees.0.id', $agent->id));
        $this->patch('/account-statuses/'.$other->id, ['status' => 'floating'])->assertForbidden();
        $this->patch('/account-statuses/'.$agent->id, ['status' => 'floating'])->assertSessionHasNoErrors();
        $this->actingAs($agent)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $qa = User::factory()->create(['role' => 'qa_admin', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($qa)->patch('/account-statuses/'.$admin->id, ['status' => 'suspended'])->assertForbidden();
        $this->patch('/account-statuses/'.$agent->id, ['status' => 'active'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch('/account-statuses/'.$admin->id, ['status' => 'terminated'])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Account status changed', 'target_id' => $agent->id]);
    }

    public function test_qa_can_pass_and_fail_trainees_without_converting_or_creating_employee_accounts(): void
    {
        $qa = User::factory()->create(['role' => 'qa_admin', 'status' => 'active']);
        $trainee = $this->trainee();
        $this->actingAs($qa)->patch('/account-statuses/'.$trainee->id, ['status' => 'graduated'])->assertSessionHasNoErrors();
        $this->assertSame('trainee', $trainee->fresh()->role);
        $this->assertSame('DVXTR001', $trainee->fresh()->username);
        $this->assertNull($trainee->employeeAccount);
        $this->post('/trainees/'.$trainee->id.'/employee-account')->assertForbidden();
        $this->actingAs($trainee->fresh())->get('/my-attendance')->assertOk();
        $this->postJson('/my-attendance/challenge', ['action' => 'time_in'])->assertUnprocessable();
        $this->actingAs($qa)->patch('/account-statuses/'.$trainee->id, ['status' => 'rejected'])->assertSessionHasNoErrors();
        $this->actingAs($trainee)->getJson('/my-attendance/status')->assertForbidden();
        $this->assertGuest();
        $this->post('/login', ['email' => $trainee->username, 'password' => 'training-password'])->assertSessionHasErrors('email');
    }

    public function test_separate_employee_copies_information_and_photo_but_preserves_trainee_credentials(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $trainee = $this->trainee();
        $trainee->forceFill(['training_status' => 'graduated', 'profile_photo_path' => 'profile-photos/original.png'])->save();
        Storage::disk('local')->put('profile-photos/original.png', 'photo');
        $trainee->faceCredential()->create(['encrypted_descriptor' => [0.1, 0.2], 'model_version' => 'test', 'consented_at' => now(), 'enrolled_at' => now()]);
        $trainee->personalInformation()->create(['email' => $trainee->email, 'birth_date' => '2000-01-01', 'start_date' => '2026-09-01', 'gender' => 'Other', 'civil_status' => 'Single', 'phone' => '09123456789', 'address' => 'Test address', 'emergency_contact_name' => 'Contact', 'emergency_contact_relationship' => 'Parent', 'emergency_contact_phone' => '09123456788']);
        AttendanceRecord::create(['user_id' => $trainee->id, 'attendance_date' => '2026-09-01', 'status' => 'present']);
        $employee = app(TraineeEmployeeAccountService::class)->create($admin, $trainee);
        $this->assertSame($trainee->email, $employee->email);
        $this->assertSame($trainee->name, $employee->name);
        $this->assertSame($trainee->personalInformation->phone, $employee->personalInformation->phone);
        $this->assertNotSame($trainee->personalInformation->id, $employee->personalInformation->id);
        $this->assertSame(0, $employee->attendanceRecords()->count());
        $this->assertSame(1, $trainee->attendanceRecords()->count());
        $this->assertTrue(Hash::check($employee->username, $employee->password));
        $this->assertTrue(Hash::check('training-password', $trainee->fresh()->password));
        $this->assertSame([0.1, 0.2], $employee->faceCredential->encrypted_descriptor);
        $this->assertNotSame($trainee->profile_photo_path, $employee->profile_photo_path);
        Storage::disk('local')->assertExists($employee->profile_photo_path);
        $this->actingAs($admin)->post('/trainees/'.$trainee->id.'/employee-account')->assertSessionHasErrors('employee');
        $this->assertSame(1, User::where('source_trainee_id', $trainee->id)->count());
    }

    public function test_shared_email_signin_and_password_resets_cannot_cross_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $trainee = $this->trainee();
        $trainee->forceFill(['training_status' => 'graduated'])->save();
        $employee = app(TraineeEmployeeAccountService::class)->create($admin, $trainee);
        $this->post('/login', ['email' => $trainee->email, 'password' => 'training-password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $trainee->username, 'password' => 'training-password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($trainee);
        $this->post('/logout');
        $token = Password::createToken($trainee);
        $payload = ['token' => $token, 'email' => $employee->username, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
        $payload['email'] = $trainee->username;
        $this->post(route('password.update'), $payload)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-123', $trainee->fresh()->password));
        $this->assertTrue(Hash::check($employee->username, $employee->fresh()->password));
    }
}
