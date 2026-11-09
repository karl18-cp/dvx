<?php

namespace App\Http\Controllers;

use App\Models\PerformanceAcknowledgement;
use App\Models\PerformanceGoal;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceMeeting;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\AssessmentNotificationService;
use App\Services\AuditLogService;
use App\Services\PerformanceAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PerformanceManagementController extends Controller
{
    public function index(Request $request, PerformanceAccessService $access)
    {
        $actor = $request->user();
        $employees = $access->visibleEmployees($actor)->orderBy('name')->get(['id', 'name', 'username', 'role', 'profile_photo_path']);
        $employee = $request->filled('employee')
            ? $employees->firstWhere('id', $request->integer('employee'))
            : $employees->firstWhere('id', $actor->id) ?? $employees->first();
        abort_unless($employee, 403);
        $employee = User::query()->findOrFail($employee->id);
        $access->authorizeView($actor, $employee);

        $goals = PerformanceGoal::query()->where('employee_id', $employee->id)->with('creator:id,name')->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'draft' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")->orderBy('due_date')->get();
        $reviews = PerformanceReview::query()->where('employee_id', $employee->id)->with(['reviewer:id,name', 'acknowledgement'])->latest('period_month')->get();
        $meetings = PerformanceMeeting::query()->where('employee_id', $employee->id)->with(['manager:id,name', 'acknowledgement'])->latest('scheduled_at')->get();
        $plans = PerformanceImprovementPlan::query()->where('employee_id', $employee->id)->with(['manager:id,name', 'sourceReview:id,period_month,overall_rating', 'acknowledgement'])->latest('start_date')->get();

        return Inertia::render('performance-management', [
            'employees' => $employees->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'role' => $user->role, 'avatar' => $user->avatar]),
            'selectedEmployee' => ['id' => $employee->id, 'name' => $employee->name, 'username' => $employee->username, 'role' => $employee->role, 'avatar' => $employee->avatar],
            'goals' => $goals,
            'reviews' => $reviews,
            'meetings' => $meetings,
            'plans' => $plans,
            'history' => $this->history($goals, $reviews, $meetings, $plans),
            'summary' => [
                'active_goals' => $goals->where('status', 'active')->count(),
                'average_goal_progress' => round((float) $goals->where('status', 'active')->avg('progress')),
                'latest_rating' => $reviews->firstWhere('status', 'finalized')?->overall_rating,
                'open_pips' => $plans->whereIn('status', ['draft', 'active'])->count(),
                'next_meeting' => $meetings->where('status', 'scheduled')->where('scheduled_at', '>=', now())->sortBy('scheduled_at')->first()?->scheduled_at?->toISOString(),
                'pending_acknowledgements' => $reviews->whereIn('status', ['shared'])->count() + $meetings->where('status', 'completed')->whereNull('acknowledgement')->count() + $plans->whereIn('status', ['active', 'completed', 'unsuccessful'])->whereNull('acknowledgement')->count(),
            ],
            'canManage' => $access->canManageEmployee($actor, $employee),
            'isSelf' => $actor->id === $employee->id,
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function storeGoal(Request $request, PerformanceAccessService $access, AuditLogService $audit)
    {
        $data = $this->goalData($request);
        $employee = User::query()->findOrFail($data['employee_id']);
        $access->authorizeView($request->user(), $employee);
        if (! $request->user()->is($employee)) {
            $access->authorizeManage($request->user(), $employee);
        }
        $goal = PerformanceGoal::create([...$data, 'created_by' => $request->user()->id, 'status' => 'active', 'progress' => 0]);
        $audit->record($request->user(), 'performance', 'Performance goal created', $goal, subjectId: $employee->id, metadata: ['title' => $goal->title, 'due_date' => $goal->due_date?->format('Y-m-d')]);

        return back()->with('status', 'Performance goal added.');
    }

    public function updateGoal(Request $request, PerformanceGoal $goal, PerformanceAccessService $access, AuditLogService $audit)
    {
        $access->authorizeView($request->user(), $goal->employee);
        abort_if(in_array($goal->status, ['completed', 'cancelled'], true), 422, 'Closed goals are read-only.');
        $manager = $access->canManageEmployee($request->user(), $goal->employee);
        abort_unless($manager || $request->user()->id === $goal->employee_id, 403);
        $rules = [
            'progress' => ['required', 'integer', 'between:0,100'],
            'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
            'outcome' => ['nullable', 'string', 'max:5000'],
        ];
        if ($manager) {
            $rules = [...$rules, 'title' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'target_value' => ['nullable', 'numeric'], 'unit' => ['nullable', 'string', 'max:40'], 'weight' => ['nullable', 'integer', 'between:0,100'], 'due_date' => ['sometimes', 'date', 'after_or_equal:start_date']];
        }
        $data = $request->validate($rules);
        if ($data['status'] === 'completed') {
            $data['progress'] = 100;
            $data['completed_at'] = now();
        } elseif ($data['status'] === 'cancelled') {
            $data['completed_at'] = now();
        }
        $goal->update($data);
        $audit->record($request->user(), 'performance', 'Performance goal updated', $goal, subjectId: $goal->employee_id, metadata: ['status' => $goal->status, 'progress' => $goal->progress]);

        return back()->with('status', 'Goal progress updated.');
    }

    public function storeReview(Request $request, PerformanceAccessService $access, AuditLogService $audit)
    {
        $data = $this->reviewData($request);
        $employee = User::query()->findOrFail($data['employee_id']);
        $access->authorizeManage($request->user(), $employee);
        $period = Carbon::parse($data['period_month'])->startOfMonth()->toDateString();
        if (PerformanceReview::query()->where('employee_id', $employee->id)->whereDate('period_month', $period)->exists()) {
            throw ValidationException::withMessages(['period_month' => 'A KPI review already exists for this employee and month.']);
        }
        $review = PerformanceReview::create([...$data, 'period_month' => $period, 'reviewer_id' => $request->user()->id, 'status' => 'draft']);
        $audit->record($request->user(), 'performance', 'Monthly KPI review created', $review, subjectId: $employee->id, metadata: ['period_month' => $period, 'rating' => $review->overall_rating]);

        return back()->with('status', 'Monthly KPI review saved as draft.');
    }

    public function updateReview(Request $request, PerformanceReview $review, PerformanceAccessService $access, AuditLogService $audit)
    {
        $access->authorizeManage($request->user(), $review->employee);
        abort_unless($review->status === 'draft', 422, 'Only draft reviews can be edited.');
        $data = $this->reviewData($request, false);
        unset($data['employee_id'], $data['period_month']);
        $review->update($data);
        $audit->record($request->user(), 'performance', 'Monthly KPI review updated', $review, subjectId: $review->employee_id);

        return back()->with('status', 'Review draft updated.');
    }

    public function transitionReview(Request $request, PerformanceReview $review, PerformanceAccessService $access, AssessmentNotificationService $notifications, AuditLogService $audit)
    {
        $access->authorizeManage($request->user(), $review->employee);
        $data = $request->validate(['action' => ['required', Rule::in(['share', 'finalize'])]]);
        if ($data['action'] === 'share') {
            abort_unless($review->status === 'draft', 422, 'Only draft reviews can be shared.');
            $review->update(['status' => 'shared', 'shared_at' => now()]);
            $notifications->createForUser($review->employee, "performance-review:{$review->id}:shared", 'performance_review', 'Monthly KPI review ready', 'Your monthly performance review is ready for acknowledgement.', '/performance');
        } else {
            abort_unless($review->status === 'acknowledged' && $review->acknowledgement()->exists(), 422, 'The employee must acknowledge the review before it can be finalized.');
            $review->update(['status' => 'finalized', 'finalized_at' => now()]);
        }
        $audit->record($request->user(), 'performance', 'Monthly KPI review '.$data['action'].'d', $review, subjectId: $review->employee_id, metadata: ['status' => $review->status]);

        return back()->with('status', $data['action'] === 'share' ? 'Review shared for employee acknowledgement.' : 'Review finalized and added to appraisal history.');
    }

    public function storeMeeting(Request $request, PerformanceAccessService $access, AssessmentNotificationService $notifications, AuditLogService $audit)
    {
        $data = $this->meetingData($request);
        $employee = User::query()->findOrFail($data['employee_id']);
        $access->authorizeManage($request->user(), $employee);
        $meeting = PerformanceMeeting::create([...$data, 'manager_id' => $request->user()->id, 'status' => 'scheduled']);
        $notifications->createForUser($employee, "performance-meeting:{$meeting->id}:scheduled", 'performance_meeting', 'One-on-one meeting scheduled', 'A new performance one-on-one has been scheduled for '.Carbon::parse($meeting->scheduled_at)->timezone('Asia/Manila')->format('M j, Y g:i A').'.', '/performance');
        $audit->record($request->user(), 'performance', 'Performance one-on-one scheduled', $meeting, subjectId: $employee->id, metadata: ['scheduled_at' => $meeting->scheduled_at?->toISOString()]);

        return back()->with('status', 'One-on-one meeting scheduled.');
    }

    public function updateMeeting(Request $request, PerformanceMeeting $meeting, PerformanceAccessService $access, AuditLogService $audit)
    {
        $access->authorizeManage($request->user(), $meeting->employee);
        abort_if(in_array($meeting->status, ['completed', 'cancelled'], true), 422, 'Closed meetings are read-only.');
        $data = $this->meetingData($request, false);
        unset($data['employee_id']);
        $data['status'] = $request->validate(['status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])]])['status'];
        if ($data['status'] === 'completed') {
            $data['held_at'] = now();
        }
        $meeting->update($data);
        $audit->record($request->user(), 'performance', 'Performance one-on-one updated', $meeting, subjectId: $meeting->employee_id, metadata: ['status' => $meeting->status]);

        return back()->with('status', $meeting->status === 'completed' ? 'Meeting completed and ready for acknowledgement.' : 'Meeting updated.');
    }

    public function storePlan(Request $request, PerformanceAccessService $access, AssessmentNotificationService $notifications, AuditLogService $audit)
    {
        $data = $this->planData($request);
        $employee = User::query()->findOrFail($data['employee_id']);
        $access->authorizeManage($request->user(), $employee);
        if ($data['source_review_id'] ?? null) {
            abort_unless(PerformanceReview::query()->whereKey($data['source_review_id'])->where('employee_id', $employee->id)->exists(), 422, 'The selected review belongs to another employee.');
        }
        $plan = PerformanceImprovementPlan::create([...$data, 'manager_id' => $request->user()->id, 'status' => 'active']);
        $notifications->createForUser($employee, "performance-pip:{$plan->id}:active", 'performance_pip', 'Performance improvement plan issued', 'A performance improvement plan has been assigned and requires your acknowledgement.', '/performance');
        $audit->record($request->user(), 'performance', 'Performance improvement plan issued', $plan, subjectId: $employee->id, metadata: ['end_date' => $plan->end_date?->format('Y-m-d')]);

        return back()->with('status', 'Performance improvement plan activated.');
    }

    public function updatePlan(Request $request, PerformanceImprovementPlan $plan, PerformanceAccessService $access, AuditLogService $audit)
    {
        $access->authorizeManage($request->user(), $plan->employee);
        abort_if(in_array($plan->status, ['completed', 'unsuccessful', 'cancelled'], true), 422, 'Closed improvement plans are read-only.');
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'completed', 'unsuccessful', 'cancelled'])],
            'progress_note' => ['nullable', 'string', 'max:5000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'review_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:review_date'],
        ]);
        if ($data['progress_note'] ?? null) {
            $updates = $plan->progress_updates ?? [];
            $updates[] = ['note' => $data['progress_note'], 'recorded_at' => now()->toISOString(), 'recorded_by' => $request->user()->id];
            $data['progress_updates'] = $updates;
        }
        unset($data['progress_note']);
        if (in_array($data['status'], ['completed', 'unsuccessful', 'cancelled'], true)) {
            $data['closed_at'] = now();
        }
        $plan->update($data);
        $audit->record($request->user(), 'performance', 'Performance improvement plan updated', $plan, subjectId: $plan->employee_id, metadata: ['status' => $plan->status]);

        return back()->with('status', 'Improvement plan updated.');
    }

    public function acknowledge(Request $request, string $type, int $id, AuditLogService $audit)
    {
        abort_unless(in_array($type, ['review', 'meeting', 'pip'], true), 404);
        $model = match ($type) {
            'review' => PerformanceReview::query()->findOrFail($id),
            'meeting' => PerformanceMeeting::query()->findOrFail($id),
            'pip' => PerformanceImprovementPlan::query()->findOrFail($id),
        };
        abort_unless($request->user()->id === $model->employee_id, 403);
        $allowed = match ($type) {
            'review' => $model->status === 'shared',
            'meeting' => $model->status === 'completed',
            'pip' => in_array($model->status, ['active', 'completed', 'unsuccessful'], true),
        };
        abort_unless($allowed, 422, 'This record is not ready for acknowledgement.');
        $data = $request->validate(['typed_name' => ['required', 'string', 'max:255'], 'employee_comment' => ['nullable', 'string', 'max:5000']]);
        if (mb_strtolower(trim($data['typed_name'])) !== mb_strtolower(trim($request->user()->name))) {
            throw ValidationException::withMessages(['typed_name' => 'Type your complete account name exactly as shown.']);
        }
        $acknowledgement = PerformanceAcknowledgement::firstOrCreate(
            ['employee_id' => $request->user()->id, 'record_type' => $type, 'record_id' => $model->id],
            [...$data, 'ip_address' => $request->ip(), 'acknowledged_at' => now()],
        );
        if ($type === 'review' && $acknowledgement->wasRecentlyCreated) {
            $model->update(['status' => 'acknowledged', 'employee_comments' => $data['employee_comment'] ?? null]);
        }
        $audit->record($request->user(), 'performance', 'Performance record acknowledged', $model, subjectId: $request->user()->id, metadata: ['record_type' => $type]);

        return back()->with('status', 'Acknowledgement recorded.');
    }

    private function goalData(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['performance', 'quality', 'productivity', 'attendance', 'development', 'leadership'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'baseline_value' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:40'],
            'weight' => ['required', 'integer', 'between:0,100'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
    }

    private function reviewData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'employee_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'period_month' => [$creating ? 'required' : 'sometimes', 'date_format:Y-m'],
            'overall_rating' => ['required', 'numeric', 'between:1,5'],
            'kpis' => ['required', 'array', 'min:1', 'max:20'],
            'kpis.*.name' => ['required', 'string', 'max:120'],
            'kpis.*.target' => ['nullable', 'string', 'max:120'],
            'kpis.*.actual' => ['nullable', 'string', 'max:120'],
            'kpis.*.weight' => ['required', 'integer', 'between:0,100'],
            'kpis.*.rating' => ['required', 'numeric', 'between:1,5'],
            'kpis.*.comment' => ['nullable', 'string', 'max:1000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'development_areas' => ['nullable', 'string', 'max:5000'],
            'manager_summary' => ['required', 'string', 'max:5000'],
        ]);
    }

    private function meetingData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'employee_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'agenda' => ['required', 'string', 'max:5000'],
            'manager_notes' => ['nullable', 'string', 'max:10000'],
            'action_items' => ['nullable', 'array', 'max:30'],
            'action_items.*' => ['required', 'string', 'max:500'],
            'next_meeting_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
    }

    private function planData(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'source_review_id' => ['nullable', 'integer', 'exists:performance_reviews,id'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:5000'],
            'expectations' => ['required', 'string', 'max:10000'],
            'support_plan' => ['nullable', 'string', 'max:10000'],
            'success_criteria' => ['required', 'array', 'min:1', 'max:20'],
            'success_criteria.*' => ['required', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'review_date' => ['required', 'date', 'after_or_equal:start_date'],
            'end_date' => ['required', 'date', 'after_or_equal:review_date'],
        ]);
    }

    private function history($goals, $reviews, $meetings, $plans): array
    {
        return collect()
            ->concat($reviews->where('status', 'finalized')->map(fn ($item) => ['type' => 'appraisal', 'id' => $item->id, 'title' => 'Monthly KPI appraisal · '.$item->period_month?->format('F Y'), 'date' => $item->finalized_at?->toISOString(), 'status' => $item->status, 'rating' => $item->overall_rating, 'acknowledged' => (bool) $item->acknowledgement]))
            ->concat($goals->whereIn('status', ['completed', 'cancelled'])->map(fn ($item) => ['type' => 'goal', 'id' => $item->id, 'title' => $item->title, 'date' => $item->completed_at?->toISOString() ?? $item->updated_at?->toISOString(), 'status' => $item->status, 'rating' => null, 'acknowledged' => null]))
            ->concat($meetings->whereIn('status', ['completed', 'cancelled'])->map(fn ($item) => ['type' => 'meeting', 'id' => $item->id, 'title' => 'One-on-one meeting', 'date' => $item->held_at?->toISOString() ?? $item->updated_at?->toISOString(), 'status' => $item->status, 'rating' => null, 'acknowledged' => (bool) $item->acknowledgement]))
            ->concat($plans->whereIn('status', ['completed', 'unsuccessful', 'cancelled'])->map(fn ($item) => ['type' => 'pip', 'id' => $item->id, 'title' => $item->title, 'date' => $item->closed_at?->toISOString() ?? $item->updated_at?->toISOString(), 'status' => $item->status, 'rating' => null, 'acknowledged' => (bool) $item->acknowledgement]))
            ->sortByDesc('date')->values()->all();
    }
}
