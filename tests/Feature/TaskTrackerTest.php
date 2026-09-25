<?php

namespace Tests\Feature;

use App\Models\TrackerTask;
use App\Models\TrackerTaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function payload(array $people, array $checklist = ['Review campaign report', 'Send summary']): array
    {
        return ['request_id' => (string) Str::uuid(), 'title' => 'Weekly review', 'description' => 'Review the weekly campaign performance.', 'assignee_ids' => array_map(fn ($person) => $person->id, $people), 'checklist' => $checklist];
    }

    private function action(User $actor, TrackerTaskAssignment $assignment, string $action, array $extra = [])
    {
        return $this->actingAs($actor)->patch("/task-tracker/{$assignment->tracker_task_id}/assignments/{$assignment->id}", ['action' => $action, 'version' => $assignment->fresh()->version, ...$extra]);
    }

    public function test_admin_assigns_to_multiple_eligible_employees_without_duplicate_creation(): void
    {
        $admin = $this->user('admin');
        $leader = $this->user('team_leader');
        $coadmin = $this->user('admin');
        $this->user('agent');
        $this->actingAs($admin)->get('/task-tracker')->assertInertia(fn (Assert $page) => $page->component('task-tracker')->has('assignees', 2));
        $payload = $this->payload([$leader, $coadmin]);
        $this->post('/task-tracker', $payload)->assertRedirect('/task-tracker')->assertSessionHasNoErrors();
        $this->post('/task-tracker', $payload)->assertRedirect('/task-tracker');
        $this->assertDatabaseCount('tracker_tasks', 1);
        $this->assertDatabaseCount('tracker_task_assignments', 2);
        $this->assertCount(2, TrackerTask::sole()->checklist);
    }

    public function test_ineligible_assignees_and_self_assignment_are_rejected(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        foreach (['agent', 'manager', 'it_admin', 'it_support', 'it_developer'] as $role) {
            $this->post('/task-tracker', $this->payload([$this->user($role)]))->assertSessionHasErrors('assignee_ids.0');
        }
        $inactive = $this->user('team_leader');
        $inactive->update(['status' => 'inactive']);
        $this->post('/task-tracker', $this->payload([$inactive]))->assertSessionHasErrors('assignee_ids.0');
        $this->post('/task-tracker', $this->payload([$admin]))->assertSessionHasErrors('assignee_ids.0');
        $this->assertDatabaseCount('tracker_tasks', 0);
    }

    public function test_only_admins_and_team_leaders_have_access_and_only_admins_create(): void
    {
        $leader = $this->user('team_leader');
        $this->actingAs($leader)->get('/task-tracker')->assertOk();
        $this->post('/task-tracker', $this->payload([$leader]))->assertForbidden();
        foreach (['agent', 'manager', 'it_admin', 'it_support', 'it_developer'] as $role) {
            $this->actingAs($this->user($role))->get('/task-tracker')->assertForbidden();
            $this->post('/task-tracker', $this->payload([$leader]))->assertForbidden();
        }
        $inactive = $this->user('admin');
        $inactive->update(['status' => 'inactive']);
        $this->actingAs($inactive)->get('/task-tracker')->assertForbidden();
    }

    public function test_team_leaders_only_see_their_assignments_and_cannot_change_others(): void
    {
        $admin = $this->user('admin');
        $first = $this->user('team_leader');
        $second = $this->user('team_leader');
        $this->actingAs($admin)->post('/task-tracker', $this->payload([$first, $second]));
        $shared = TrackerTask::sole();
        $this->post('/task-tracker', $this->payload([$second]));
        $this->actingAs($first)->get('/task-tracker')->assertInertia(fn (Assert $page) => $page->where('tasks.total', 1)->has('tasks.data.0.assignments', 1)->where('tasks.data.0.assignments.0.user_id', $first->id)->has('assignees', 0));
        $other = $shared->assignments()->where('user_id', $second->id)->first();
        $this->action($first, $other, 'checklist', ['completed_items' => []])->assertForbidden();
        $this->actingAs($first)->delete("/task-tracker/{$shared->id}")->assertForbidden();
    }

    public function test_checklists_are_independent_and_completion_requires_admin_approval(): void
    {
        $admin = $this->user('admin');
        $leader = $this->user('team_leader');
        $coadmin = $this->user('admin');
        $this->actingAs($admin)->post('/task-tracker', $this->payload([$leader, $coadmin]));
        $task = TrackerTask::sole();
        $assignment = $task->assignments()->where('user_id', $leader->id)->first();
        $this->action($leader, $assignment, 'submit')->assertSessionHasErrors('task');
        $this->action($leader, $assignment, 'checklist', ['completed_items' => array_column($task->checklist, 'id')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $task->assignments()->where('user_id', $coadmin->id)->first()->completed_items);
        $this->action($leader, $assignment, 'submit')->assertRedirect()->assertSessionHasNoErrors();
        $this->action($leader, $assignment, 'checklist', ['completed_items' => []])->assertSessionHasErrors('task');
        $this->action($leader, $assignment, 'approve')->assertForbidden();
        $this->action($admin, $assignment, 'return', ['review_notes' => 'Add campaign details'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $assignment->fresh()->status);
        $this->action($leader, $assignment, 'submit')->assertSessionHasNoErrors();
        $this->action($admin, $assignment, 'approve')->assertSessionHasNoErrors();
        $this->assertSame('approved_done', $assignment->fresh()->status);
        $this->assertSame($admin->id, $assignment->fresh()->reviewed_by);
    }

    public function test_coadmins_can_complete_but_not_approve_their_own_tasks(): void
    {
        $creator = $this->user('admin');
        $assignee = $this->user('admin');
        $this->actingAs($creator)->post('/task-tracker', $this->payload([$assignee], []));
        $assignment = TrackerTaskAssignment::sole();
        $this->action($creator, $assignment, 'submit')->assertForbidden();
        $this->action($assignee, $assignment, 'submit')->assertSessionHasNoErrors();
        $this->action($assignee, $assignment, 'approve')->assertForbidden();
        $this->action($creator, $assignment, 'approve')->assertSessionHasNoErrors();
        $this->assertSame('approved_done', $assignment->fresh()->status);
    }

    public function test_stale_updates_and_invalid_checklist_ids_do_not_overwrite_progress(): void
    {
        $admin = $this->user('admin');
        $leader = $this->user('team_leader');
        $this->actingAs($admin)->post('/task-tracker', $this->payload([$leader]));
        $task = TrackerTask::sole();
        $assignment = TrackerTaskAssignment::sole();
        $this->action($leader, $assignment, 'checklist', ['completed_items' => ['invalid']])->assertSessionHasErrors('completed_items.0');
        $this->action($leader, $assignment, 'checklist', ['completed_items' => [$task->checklist[0]['id']]])->assertSessionHasNoErrors();
        $this->action($leader, $assignment, 'checklist', ['version' => 1, 'completed_items' => []])->assertSessionHasErrors('task');
        $this->assertCount(1, $assignment->fresh()->completed_items);
        $leader->update(['role' => 'agent']);
        $this->action($leader, $assignment, 'submit')->assertForbidden();
    }

    public function test_deleted_tasks_disappear_and_cannot_be_updated(): void
    {
        $admin = $this->user('admin');
        $leader = $this->user('team_leader');
        $this->actingAs($admin)->post('/task-tracker', $this->payload([$leader]));
        $task = TrackerTask::sole();
        $assignment = TrackerTaskAssignment::sole();
        $this->delete("/task-tracker/{$task->id}")->assertRedirect();
        $this->assertSoftDeleted($task);
        $this->actingAs($leader)->get('/task-tracker')->assertInertia(fn (Assert $page) => $page->where('tasks.total', 0));
        $this->action($leader, $assignment, 'submit')->assertNotFound();
    }

    public function test_assignment_must_belong_to_the_task_in_the_url(): void
    {
        $admin = $this->user('admin');
        $leader = $this->user('team_leader');
        $this->actingAs($admin)->post('/task-tracker', $this->payload([$leader]));
        $assignment = TrackerTaskAssignment::sole();
        $this->post('/task-tracker', $this->payload([$leader]));
        $other = TrackerTask::latest('id')->first();
        $this->actingAs($leader)->patch("/task-tracker/{$other->id}/assignments/{$assignment->id}", ['version' => 1, 'action' => 'submit'])->assertNotFound();
    }
}
