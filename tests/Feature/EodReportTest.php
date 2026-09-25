<?php

namespace Tests\Feature;

use App\Models\EodReport;
use App\Models\TrackerTask;
use App\Models\TrackerTaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EodReportTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'username' => 'EOD'.Str::random(8)]);
    }

    private function assignment(User $person): TrackerTaskAssignment
    {
        $creator = $this->user('admin');
        $task = TrackerTask::create(['request_id' => (string) Str::uuid(), 'created_by' => $creator->id, 'creator_name' => $creator->name, 'title' => 'Daily campaign review', 'description' => 'Review the daily results.', 'checklist' => [['id' => 'first', 'label' => 'Review report'], ['id' => 'second', 'label' => 'Send summary']]]);

        return $task->assignments()->create(['user_id' => $person->id, 'assignee_name' => $person->name, 'assignee_role' => $person->role, 'completed_items' => ['first']])->fresh();
    }

    private function payload(TrackerTaskAssignment $assignment): array
    {
        return ['request_id' => (string) Str::uuid(), 'report_date' => now('Asia/Manila')->toDateString(), 'summary' => 'Reviewed results and prepared tomorrow’s action plan.', 'blockers' => 'Waiting for final numbers.', 'next_steps' => 'Send the final summary.', 'tasks' => [['assignment_id' => $assignment->id, 'version' => $assignment->version, 'notes' => 'Completed the first review.']]];
    }

    public function test_admins_and_team_leaders_create_own_reports_from_real_tasks(): void
    {
        foreach (['admin', 'team_leader'] as $role) {
            $person = $this->user($role);
            $assignment = $this->assignment($person);
            $data = $this->payload($assignment);
            $data['user_id'] = 999999;
            $data['task_snapshots'] = [['title' => 'Forged', 'status' => 'approved_done']];
            $this->actingAs($person)->post('/eod-reports', $data)->assertRedirect('/eod-reports')->assertSessionHasNoErrors();
            $report = EodReport::latest('id')->first();
            $this->assertSame($person->id, $report->user_id);
            $this->assertSame($role, $report->author_role);
            $this->assertSame('Daily campaign review', $report->task_snapshots[0]['title']);
            $this->assertSame('open', $report->task_snapshots[0]['status']);
            $this->assertTrue($report->task_snapshots[0]['checklist'][0]['completed']);
            $this->assertFalse($report->task_snapshots[0]['checklist'][1]['completed']);
        }
    }

    public function test_admin_can_read_every_report_and_team_leader_only_their_own(): void
    {
        $admin = $this->user('admin');
        $leader = $this->user('team_leader');
        $other = $this->user('team_leader');
        foreach ([$admin, $leader, $other] as $person) {
            $this->actingAs($person)->post('/eod-reports', $this->payload($this->assignment($person)))->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)->get('/eod-reports')->assertOk()->assertInertia(fn (Assert $page) => $page->component('eod-reports')->where('reports.total', 3)->has('assignments', 1));
        $this->actingAs($leader)->get('/eod-reports')->assertInertia(fn (Assert $page) => $page->where('reports.total', 1)->where('reports.data.0.user_id', $leader->id)->has('assignments', 1));
        $foreign = EodReport::where('user_id', $other->id)->first();
        $this->getJson("/eod-reports/{$foreign->id}")->assertForbidden();
        $this->actingAs($admin)->getJson("/eod-reports/{$foreign->id}")->assertOk()->assertJsonPath('author_name', $other->name);
        $own = EodReport::where('user_id', $leader->id)->first();
        $this->actingAs($leader)->getJson("/eod-reports/{$own->id}")->assertOk();
    }

    public function test_other_roles_are_denied_and_cannot_submit_or_read_details(): void
    {
        $admin = $this->user('admin');
        $assignment = $this->assignment($admin);
        $this->actingAs($admin)->post('/eod-reports', $this->payload($assignment));
        $report = EodReport::sole();
        foreach (['agent', 'manager', 'it_admin', 'it_support', 'it_developer'] as $role) {
            $this->actingAs($this->user($role))->get('/eod-reports')->assertForbidden();
            $this->post('/eod-reports', $this->payload($assignment))->assertForbidden();
            $this->getJson("/eod-reports/{$report->id}")->assertForbidden();
        }
        $admin->update(['status' => 'inactive']);
        $this->actingAs($admin)->get('/eod-reports')->assertForbidden();
    }

    public function test_foreign_deleted_and_stale_assignments_are_rejected(): void
    {
        $leader = $this->user('team_leader');
        $foreign = $this->assignment($this->user('admin'));
        $this->actingAs($leader)->post('/eod-reports', $this->payload($foreign))->assertSessionHasErrors('tasks');
        $own = $this->assignment($leader);
        $data = $this->payload($own);
        $own->increment('version');
        $this->post('/eod-reports', $data)->assertSessionHasErrors('tasks');
        $own->task->delete();
        $this->post('/eod-reports', $this->payload($own->fresh()))->assertSessionHasErrors('tasks');
        $this->assertDatabaseCount('eod_reports', 0);
    }

    public function test_snapshots_survive_task_changes_and_deletion(): void
    {
        $leader = $this->user('team_leader');
        $assignment = $this->assignment($leader);
        $this->actingAs($leader)->post('/eod-reports', $this->payload($assignment))->assertSessionHasNoErrors();
        $assignment->completed_items = ['first', 'second'];
        $assignment->status = 'approved_done';
        $assignment->save();
        $assignment->task->update(['title' => 'Changed task']);
        $assignment->task->delete();
        $report = EodReport::sole();
        $this->getJson("/eod-reports/{$report->id}")->assertOk()->assertJsonPath('task_snapshots.0.title', 'Daily campaign review')->assertJsonPath('task_snapshots.0.status', 'open')->assertJsonPath('task_snapshots.0.completed_count', 1);
    }

    public function test_duplicate_submissions_do_not_create_duplicate_reports(): void
    {
        $admin = $this->user('admin');
        $data = $this->payload($this->assignment($admin));
        $this->actingAs($admin)->post('/eod-reports', $data)->assertSessionHasNoErrors();
        $this->post('/eod-reports', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('eod_reports', 1);
    }

    public function test_report_date_and_required_content_are_validated(): void
    {
        $leader = $this->user('team_leader');
        $data = $this->payload($this->assignment($leader));
        $this->actingAs($leader)->post('/eod-reports', [...$data, 'report_date' => now('Asia/Manila')->addDay()->toDateString()])->assertSessionHasErrors('report_date');
        $this->post('/eod-reports', [...$data, 'report_date' => now('Asia/Manila')->subDays(2)->toDateString()])->assertSessionHasErrors('tasks');
        $this->post('/eod-reports', [...$data, 'tasks' => []])->assertSessionHasErrors('tasks');
        $this->post('/eod-reports', [...$data, 'summary' => ''])->assertSessionHasErrors('summary');
        $this->assertDatabaseCount('eod_reports', 0);
    }

    public function test_report_filters_work_and_allow_an_end_date_without_start_date(): void
    {
        $admin = $this->user('admin');
        $leader = $this->user('team_leader');
        foreach ([$admin, $leader] as $person) {
            $this->actingAs($person)->post('/eod-reports', $this->payload($this->assignment($person)));
        }
        $this->actingAs($admin)->get('/eod-reports?role=team_leader&to='.now('Asia/Manila')->toDateString())->assertOk()->assertInertia(fn (Assert $page) => $page->where('reports.total', 1)->where('reports.data.0.user_id', $leader->id));
        $this->get('/eod-reports?search=does-not-exist')->assertInertia(fn (Assert $page) => $page->where('reports.total', 0));
    }
}
