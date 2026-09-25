<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AttendanceRecord;
use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TraineePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduation_updates_an_unchanged_temporary_password_to_the_new_username(): void
    {
        $trainee = $this->trainee();
        $trainee->forceFill(['username' => 'DVXTR001', 'password' => 'DVXTR001'])->save();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'DVX005']);
        $this->actingAs($admin)->patch('/trainees/'.$trainee->id.'/review', ['decision' => 'graduated'])->assertRedirect();
        $trainee->refresh();
        $this->assertSame('DVX006', $trainee->username);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('DVX006', $trainee->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('DVXTR001', $trainee->password));
    }

    public function test_trainee_numbers_do_not_consume_official_numbers_and_graduation_allocates_next_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'DVX009']);
        $trainee = $this->trainee();
        $trainee->forceFill(['username' => 'DVXTR001'])->save();
        User::factory()->create(['role' => 'trainee', 'username' => 'DVXTR999']);
        $numbers = app(\App\Services\EmployeeNumberService::class);
        $this->assertSame('DVX010', $numbers->next());
        $this->assertSame('DVXTR1000', $numbers->next(trainee: true));
        $password = $trainee->password;
        $this->actingAs($admin)->patch('/trainees/'.$trainee->id.'/review', ['decision' => 'graduated'])->assertRedirect();
        $this->assertSame('DVX010', $trainee->fresh()->username);
        $this->assertSame($password, $trainee->fresh()->password);
        $this->assertSame('DVX011', $numbers->next());
        $this->patch('/trainees/'.$trainee->id.'/review', ['decision' => 'graduated'])->assertStatus(409);
        $this->assertSame('DVX011', $numbers->next());
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'trainee.graduated', 'target_id' => $trainee->id]);
    }

    public function test_number_allocation_rolls_back_and_does_not_reuse_consumed_numbers(): void
    {
        $numbers = app(\App\Services\EmployeeNumberService::class);
        \Illuminate\Support\Facades\DB::beginTransaction();
        $this->assertSame('DVX002', $numbers->next(allocate: true));
        \Illuminate\Support\Facades\DB::rollBack();
        $this->assertSame('DVX002', $numbers->next());
        \Illuminate\Support\Facades\DB::transaction(fn () => $numbers->next(allocate: true));
        $this->assertSame('DVX003', $numbers->next());
    }

    private function trainee(): User
    {
        $campaign = Campaign::create(['name' => 'Training campaign', 'abbreviation' => 'TC', 'is_active' => true]);
        $user = User::factory()->create(['role' => 'trainee', 'status' => 'active']);
        $user->forceFill(['training_campaign_id' => $campaign->id, 'training_status' => 'in_training'])->save();

        return $user;
    }

    public function test_trainee_has_only_personal_workspace_and_cannot_open_management_or_agent_extras(): void
    {
        $trainee = $this->trainee();
        $this->actingAs($trainee)->get('/dashboard')->assertRedirect('/my-attendance');
        foreach (['/my-attendance', '/assessments', '/my-coaching', '/settings/profile'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/trainees', '/employees', '/attendance', '/ranking', '/my-team', '/my-records', '/my-requests', '/leave-requests', '/task-tracker', '/divertext', '/management/coaching'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $other = User::factory()->create();
        AttendanceRecord::create(['user_id' => $other->id, 'attendance_date' => '2026-09-25', 'status' => 'absent']);
        $this->getJson('/my-attendance/records?date=2026-09-25&user_id='.$other->id)->assertOk()->assertJsonPath('times.0.actual', null);
        $this->postJson('/my-attendance/challenge', ['action' => 'time_in'])->assertUnprocessable();
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_admin_can_assign_campaign_training_and_coaching_without_a_production_team(): void
    {
        $trainee = $this->trainee();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $assessment = Assessment::create(['title' => 'Trainee assessment', 'status' => 'published', 'created_by' => $admin->id, 'applies_to_all_campaigns' => false]);
        $assessment->campaigns()->attach($trainee->training_campaign_id);
        $this->actingAs($admin)->post(route('assessments.assignments.store'), ['assessment_id' => $assessment->id, 'employee_ids' => [$trainee->id], 'all_employees' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assessment_assignments', ['employee_id' => $trainee->id, 'campaign_id' => $trainee->training_campaign_id]);
        $this->post(route('coaching.manage.store'), ['employee_id' => $trainee->id, 'type' => \App\Support\CoachingOptions::TYPES[0], 'coaching_date' => '2026-09-25', 'summary' => 'Training feedback'])->assertRedirect()->assertSessionHasNoErrors();
        $record = CoachingRecord::sole();
        $this->assertSame($trainee->training_campaign_id, $record->campaign_id);
        $this->actingAs($trainee)->get('/my-coaching')->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
        $this->get('/my-coaching/'.$record->id)->assertOk();
        $other = User::factory()->create(['role' => 'trainee']);
        $other->forceFill(['training_campaign_id' => $trainee->training_campaign_id, 'training_status' => 'in_training'])->save();
        $this->actingAs($other)->get('/my-coaching/'.$record->id)->assertForbidden();
        $this->post('/my-coaching/'.$record->id.'/acknowledge')->assertForbidden();
    }

    public function test_graduation_is_admin_only_once_and_preserves_history(): void
    {
        $trainee = $this->trainee();
        AttendanceRecord::create(['user_id' => $trainee->id, 'attendance_date' => '2026-09-25', 'status' => 'present']);
        $leader = User::factory()->create(['role' => 'team_leader']);
        $url = '/trainees/'.$trainee->id.'/review';
        $this->actingAs($leader)->patch($url, ['decision' => 'graduated'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->patch('/employees/'.$trainee->id.'/role', ['position' => 'Agent'])->assertSessionHasErrors('position');
        $this->patch($url, ['decision' => 'graduated'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $trainee->id, 'role' => 'agent', 'training_status' => 'graduated', 'training_reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('attendance_records', ['user_id' => $trainee->id]);
        $this->patch($url, ['decision' => 'rejected', 'notes' => 'Repeat'])->assertStatus(409);
        $this->actingAs($trainee->fresh())->get('/my-records')->assertOk();
    }

    public function test_rejection_requires_reason_and_blocks_existing_sessions_and_opaque_urls(): void
    {
        $trainee = $this->trainee();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $url = '/trainees/'.$trainee->id.'/review';
        $this->actingAs($admin)->patch($url, ['decision' => 'rejected'])->assertSessionHasErrors('notes');
        $this->patch($url, ['decision' => 'rejected', 'notes' => 'Did not complete training'])->assertRedirect();
        $this->actingAs($trainee)->get('/my-attendance')->assertForbidden();
        $this->get('/my-coaching')->assertForbidden();
        $this->get('/settings/profile')->assertForbidden();
        $this->postJson('/my-attendance/challenge', ['action' => 'time_in'])->assertForbidden();
        config(['navigation.opaque_urls' => true]);
        $opaque = app(\App\Services\OpaquePageUrls::class)->encode('/my-attendance');
        $this->get($opaque)->assertForbidden();
        $this->post('/logout')->assertRedirect();
    }
}
