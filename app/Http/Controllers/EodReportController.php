<?php

namespace App\Http\Controllers;

use App\Models\EodReport;
use App\Models\TrackerTask;
use App\Models\TrackerTaskAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EodReportController extends Controller
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
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(['admin', 'team_leader'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $reports = EodReport::query()->when($actor->role !== 'admin', fn ($q) => $q->where('user_id', $actor->id));
        if ($search = trim($filters['search'] ?? '')) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $reports->where(fn ($q) => $q->where('author_name', 'like', $like)->orWhere('author_username', 'like', $like)->orWhere('summary', 'like', $like));
        }
        $reports->when($filters['role'] ?? null, fn ($q, $role) => $q->where('author_role', $role))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('report_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('report_date', '<=', $date));

        return Inertia::render('eod-reports', [
            'reports' => $reports->latest('report_date')->latest('id')->paginate(15, ['id', 'user_id', 'author_name', 'author_username', 'author_role', 'report_date', 'summary', 'task_count', 'created_at'])->withQueryString(),
            'assignments' => TrackerTaskAssignment::where('user_id', $actor->id)->whereHas('task')->with('task')->latest('id')->get()->map(fn ($assignment) => [
                'id' => $assignment->id, 'version' => $assignment->version, 'status' => $assignment->status, 'completed_items' => $assignment->completed_items,
                'title' => $assignment->task->title, 'description' => $assignment->task->description, 'checklist' => $assignment->task->checklist, 'assigned_at' => $assignment->created_at->toISOString(),
            ]),
            'isAdmin' => $actor->role === 'admin', 'today' => now('Asia/Manila')->toDateString(),
            'filters' => ['search' => $filters['search'] ?? '', 'role' => $filters['role'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request)
    {
        $actor = $this->actor($request);
        $data = $request->validate([
            'request_id' => ['required', 'uuid'], 'report_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'summary' => ['required', 'string', 'max:5000'], 'blockers' => ['nullable', 'string', 'max:3000'], 'next_steps' => ['nullable', 'string', 'max:3000'],
            'tasks' => ['required', 'array', 'min:1', 'max:50'], 'tasks.*.assignment_id' => ['required', 'integer', 'distinct'], 'tasks.*.version' => ['required', 'integer', 'min:1'], 'tasks.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($actor, $data): void {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($existing = EodReport::where('request_id', $data['request_id'])->first()) {
                abort_unless($existing->user_id === $actor->id, 403);

                return;
            }
            $ids = array_column($data['tasks'], 'assignment_id');
            $taskIds = TrackerTaskAssignment::whereIn('id', $ids)->where('user_id', $actor->id)->pluck('tracker_task_id');
            $tasks = TrackerTask::whereIn('id', $taskIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $assignments = TrackerTaskAssignment::whereIn('id', $ids)->where('user_id', $actor->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $end = Carbon::parse($data['report_date'], 'Asia/Manila')->endOfDay();
            $snapshots = [];
            foreach ($data['tasks'] as $entry) {
                $assignment = $assignments->get($entry['assignment_id']);
                $task = $assignment ? $tasks->get($assignment->tracker_task_id) : null;
                if (! $task || $assignment->created_at->gt($end)) {
                    throw ValidationException::withMessages(['tasks' => 'Select your own active task assignments available on the report date. Deleted tasks cannot be included.']);
                }
                if ($assignment->version !== $entry['version']) {
                    throw ValidationException::withMessages(['tasks' => 'A task changed since this page was opened. Refresh task progress before submitting.']);
                }
                $snapshots[] = ['task_id' => $task->id, 'assignment_id' => $assignment->id, 'title' => $task->title, 'description' => $task->description, 'created_by' => $task->creator_name, 'status' => $assignment->status, 'checklist' => array_map(fn ($item) => [...$item, 'completed' => in_array($item['id'], $assignment->completed_items, true)], $task->checklist), 'completed_count' => count($assignment->completed_items), 'reviewer_name' => $assignment->reviewer_name, 'review_notes' => $assignment->review_notes, 'notes' => $entry['notes'] ?? null];
            }
            EodReport::create(['request_id' => $data['request_id'], 'user_id' => $actor->id, 'author_name' => $actor->name, 'author_username' => $actor->username, 'author_role' => $actor->role, 'report_date' => $data['report_date'], 'summary' => $data['summary'], 'blockers' => $data['blockers'] ?? null, 'next_steps' => $data['next_steps'] ?? null, 'task_snapshots' => $snapshots, 'task_count' => count($snapshots)]);
        }, 3);

        return to_route('eod-reports')->with('status', 'EOD report submitted.');
    }

    public function show(Request $request, EodReport $report)
    {
        $actor = $this->actor($request);
        abort_unless($actor->role === 'admin' || $report->user_id === $actor->id, 403);

        return response()->json($report->makeHidden('request_id'));
    }
}
