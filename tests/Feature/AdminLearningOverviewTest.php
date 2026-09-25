<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\CoachingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminLearningOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_all_assessments_and_employee_assignment_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $employee = User::factory()->create(['role' => 'agent']);
        foreach (['draft', 'published', 'archived'] as $status) {
            $assessment = Assessment::query()->create(['title' => $status.' assessment', 'status' => $status, 'created_by' => $manager->id]);
            AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $manager->id, 'assigned_at' => now(), 'status' => 'assigned']);
        }

        $this->actingAs($admin)->get('/assessments')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/learning-overview')->where('kind', 'assessments')->where('rows.total', 3)
            ->where('summary.All assessments', 3)->where('summary.Employee assignments', 3)
            ->where('rows.data.0.status', 'archived')->where('rows.data.0.details.0', '1 assigned'));
        $this->get('/assessments?search=published')->assertInertia(fn (Assert $page) => $page
            ->where('rows.total', 1)->where('summary.All assessments', 3));
    }

    public function test_admin_can_browse_and_open_other_employees_coaching_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $coach = User::factory()->create(['role' => 'manager']);
        $employee = User::factory()->create(['role' => 'agent', 'name' => 'Overview Employee']);
        foreach (range(1, 16) as $number) {
            $record = CoachingRecord::query()->create(['employee_id' => $employee->id, 'coach_id' => $coach->id, 'coaching_date' => today(), 'type' => 'Performance', 'summary' => 'Feedback', 'status' => 'open', 'follow_up_date' => today()->subDay()]);
        }

        $this->actingAs($admin)->get('/my-coaching')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/learning-overview')->where('kind', 'coaching')->where('rows.total', 16)->has('rows.data', 15)
            ->where('rows.data.0.title', 'Overview Employee')->where('summary.Overdue follow-ups', 16)
            ->where('summary.Employees coached', 1));
        $this->get('/my-coaching?search=Overview&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('rows.total', 16)->has('rows.data', 1)->where('search', 'Overview'));
        $this->get(route('coaching.manage.show', $record))->assertOk();
        $this->get('/my-coaching?search=unmatched')->assertInertia(fn (Assert $page) => $page
            ->where('rows.total', 0)->where('summary.All coaching records', 16));
    }

    public function test_non_admin_roles_keep_personal_views_and_cannot_access_others_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'agent']);
        $assessment = Assessment::query()->create(['title' => 'Private assignment', 'created_by' => $admin->id]);
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $other->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
        $record = CoachingRecord::query()->create(['employee_id' => $other->id, 'coach_id' => $admin->id, 'coaching_date' => today(), 'type' => 'Performance', 'summary' => 'Private feedback']);

        foreach (['agent', 'manager', 'team_leader'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/assessments')->assertInertia(fn (Assert $page) => $page
                ->component('assessments/index')->has('assignments', 0)->missing('summary'));
            $this->get('/my-coaching')->assertInertia(fn (Assert $page) => $page
                ->component('coaching/my-coaching')->where('records.total', 0)->missing('summary'));
            $this->get(route('coaching.my.show', $record))->assertForbidden();
            $this->post(route('coaching.my.acknowledge', $record))->assertForbidden();
            $this->post(route('assessments.my.start', $assignment))->assertForbidden();
        }
    }
}
