<?php

namespace App\Http\Controllers;

use App\Models\TrackerTask;
use App\Models\TrackerTaskAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TaskTrackerController extends Controller
{
    private function actor(Request $request): User
    {
        $actor = $request->user()?->fresh();
        abort_unless($actor && $actor->status === 'active' && in_array($actor->role, ['admin', 'team_leader'], true), 403);

        return $actor;
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['open', 'in_progress', 'pending_review', 'approved_done'])], 'scope' => ['nullable', Rule::in(['all', 'mine'])]]);
        $mine = $actor->role !== 'admin' || ($filters['scope'] ?? '') === 'mine';
        $visible = fn ($query) => $query->when($mine, fn ($q) => $q->where('user_id', $actor->id));
        $query = TrackerTask::query()->whereHas('assignments', $visible)->with(['assignments' => $visible]);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%');
        }
        if ($status = $filters['status'] ?? null) {
            $query->whereHas('assignments', fn ($q) => $visible($q)->where('status', $status));
        }

        return Inertia::render('task-tracker', [
            'tasks' => $query->latest('id')->paginate(12)->withQueryString(),
            'assignees' => $actor->role === 'admin' ? User::where('status', 'active')->whereIn('role', ['admin', 'team_leader'])->where('id', '!=', $actor->id)->orderBy('name')->get(['id', 'name', 'username', 'role']) : [],
            'isAdmin' => $actor->role === 'admin',
            'currentUserId' => $actor->id,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status ?? '', 'scope' => $mine ? 'mine' : 'all'],
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request)
    {
        $actor = $this->actor($request);
        abort_unless($actor->role === 'admin', 403);
        if (is_string($request->input('title'))) {
            $request->merge(['title' => trim($request->input('title'))]);
        }
        $data = $request->validate([
            'request_id' => ['required', 'uuid'], 'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assignee_ids' => ['required', 'array', 'min:1', 'max:100'],
            'assignee_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['admin', 'team_leader'])->where('status', 'active')->where('id', '!=', $actor->id))],
            'checklist' => ['present', 'array', 'max:100'], 'checklist.*' => ['required', 'string', 'max:250'],
        ]);
        DB::transaction(function () use ($actor, $data): void {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($existing = TrackerTask::withTrashed()->where('request_id', $data['request_id'])->first()) {
                abort_unless($existing->created_by === $actor->id, 403);

                return;
            }
            $people = User::whereIn('id', $data['assignee_ids'])->whereIn('role', ['admin', 'team_leader'])->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
            if ($people->count() !== count($data['assignee_ids'])) {
                throw ValidationException::withMessages(['assignee_ids' => 'An employee is no longer eligible. Refresh the list and select active admins or team leaders.']);
            }
            $task = TrackerTask::create(['request_id' => $data['request_id'], 'title' => $data['title'], 'description' => $data['description'] ?? null, 'created_by' => $actor->id, 'creator_name' => $actor->name, 'checklist' => array_map(fn ($label) => ['id' => (string) Str::uuid(), 'label' => trim($label)], $data['checklist'])]);
            foreach ($people as $person) {
                $task->assignments()->create(['user_id' => $person->id, 'assignee_name' => $person->name, 'assignee_role' => $person->role, 'completed_items' => []]);
            }
        }, 3);

        return to_route('task-tracker')->with('status', 'Task assigned successfully.');
    }

    public function update(Request $request, TrackerTask $task, TrackerTaskAssignment $assignment)
    {
        $actor = $this->actor($request);
        abort_unless($assignment->tracker_task_id === $task->id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['checklist', 'submit', 'approve', 'return'])], 'version' => ['required', 'integer', 'min:1'], 'completed_items' => ['sometimes', 'array'], 'completed_items.*' => ['required', 'string', 'distinct', Rule::in(array_column($task->checklist, 'id'))], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        $review = in_array($data['action'], ['approve', 'return'], true);
        abort_unless($review ? $actor->role === 'admin' && $assignment->user_id !== $actor->id : $assignment->user_id === $actor->id, 403);
        DB::transaction(function () use ($actor, $task, $assignment, $data, $review): void {
            $task = TrackerTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $assignment = TrackerTaskAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if ($assignment->version !== $data['version']) {
                throw ValidationException::withMessages(['task' => 'This task changed in another session. Refresh before updating it.']);
            }
            if ($review) {
                if ($assignment->status !== 'pending_review') {
                    throw ValidationException::withMessages(['task' => 'Only tasks submitted for review can be approved or returned.']);
                }
                $assignment->status = $data['action'] === 'approve' ? 'approved_done' : 'in_progress';
                $assignment->reviewed_by = $actor->id;
                $assignment->reviewer_name = $actor->name;
                $assignment->reviewed_at = now();
                $assignment->review_notes = $data['review_notes'] ?? null;
            } else {
                if (in_array($assignment->status, ['pending_review', 'approved_done'], true)) {
                    throw ValidationException::withMessages(['task' => 'This task is awaiting approval or already approved.']);
                }
                if ($data['action'] === 'checklist') {
                    $assignment->completed_items = $data['completed_items'] ?? [];
                    $assignment->status = count($assignment->completed_items) ? 'in_progress' : 'open';
                } else {
                    if (count($assignment->completed_items) !== count($task->checklist)) {
                        throw ValidationException::withMessages(['task' => 'Complete all checklist items before submitting this task.']);
                    }
                    $assignment->status = 'pending_review';
                    $assignment->submitted_at = now();
                }
            }
            $assignment->version++;
            $assignment->save();
        });

        return back()->with('status', 'Task updated.');
    }

    public function destroy(Request $request, TrackerTask $task)
    {
        abort_unless($this->actor($request)->role === 'admin', 403);
        DB::transaction(function () use ($task): void {
            TrackerTask::whereKey($task->id)->lockForUpdate()->firstOrFail()->delete();
        });

        return back()->with('status', 'Task deleted.');
    }
}
