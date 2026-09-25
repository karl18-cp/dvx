<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaAdminPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_admin_lands_on_qa_and_can_access_each_training_and_quality_tab(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'qa_admin', 'status' => 'active']));
        $this->get('/dashboard')->assertRedirect('/management/qa-dashboard');
        foreach (['assessments', 'assessment-assignments', 'training-library', 'question-bank', 'coaching', 'assessment-reviews', 'assessment-results', 'campaign-analytics', 'qa-dashboard', 'call-evaluations', 'call-evaluations/create', 'qa-scorecards'] as $path) {
            $this->get('/management/'.$path)->assertOk();
        }
        $this->get('/settings/profile')->assertOk();
    }

    public function test_qa_admin_cannot_access_other_portals_or_mutate_unrelated_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'qa_admin', 'status' => 'active']));
        foreach (['employees', 'teams', 'trainees', 'campaigns', 'attendance', 'ranking', 'task-tracker', 'eod-reports', 'divertext', 'my-team', 'leave-requests', 'forms', 'sanctions', 'announcements', 'management/applicants'] as $path) {
            $this->assertSame(403, $this->get('/'.$path)->status(), $path);
        }
        $this->assertContains($this->put('/management/applicants/1', [])->status(), [403, 404]);
        $this->assertContains($this->post('/management/applicants/1/email', [])->status(), [403, 404]);
        $this->post('/employees', [])->assertForbidden();
        $this->patch('/employees/1/role', ['position' => 'Admin'])->assertForbidden();
    }

    public function test_admin_can_assign_qa_role_and_qa_forms_pass_authorization(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->actingAs($admin)->patch('/employees/'.$employee->id.'/role', ['position' => 'QA Assessment Admin'])->assertSessionHasNoErrors();
        $this->assertSame('qa_admin', $employee->fresh()->role);
        $this->actingAs($employee->fresh());
        foreach (['assessments', 'qa-scorecards', 'call-evaluations', 'coaching'] as $path) {
            $this->post('/management/'.$path, [])->assertSessionHasErrors();
        }
    }

    public function test_inactive_qa_admin_is_blocked(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'qa_admin', 'status' => 'terminated']));
        $this->get('/management/qa-dashboard')->assertForbidden();
    }
}
