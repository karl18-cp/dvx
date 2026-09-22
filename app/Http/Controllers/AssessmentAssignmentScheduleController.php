<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAssignment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentAssignmentScheduleController extends Controller
{
    private function data(Request $request): array
    {
        return $request->validate([
            'assessment_id' => ['required', 'integer', Rule::exists('assessments', 'id')->where('status', 'published')],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'available_from' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:available_from'],
        ]);
    }

    private function matching(array $data)
    {
        return AssessmentAssignment::query()
            ->where('assessment_id', $data['assessment_id'])
            ->when($data['campaign_id'] ?? null, fn ($q, $id) => $q->where('campaign_id', $id))
            ->when($data['team_id'] ?? null, fn ($q, $id) => $q->where('source_team_id', $id));
    }

    private function token($assignments, array $data): string
    {
        return hash('sha256', json_encode([
            [(int) $data['assessment_id'], (int) ($data['campaign_id'] ?? 0), (int) ($data['team_id'] ?? 0), $data['available_from'], $data['due_date']],
            $assignments->map(fn ($row) => [$row->id, $row->updated_at?->toJSON(), $row->status, $row->attempts_count, $row->available_from, $row->due_date])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function preview(Request $request): array
    {
        $data = $this->data($request);
        $rows = $this->matching($data)->withCount('attempts')->orderBy('id')->get();
        $count = $rows->filter(fn ($row) => $row->status === 'assigned' && $row->attempts_count === 0)->count();

        return ['count' => $count, 'skipped' => $rows->count() - $count, 'review_token' => $this->token($rows, $data)];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $request->validate(['review_token' => ['required', 'string', 'size:64']]);
        $count = DB::transaction(function () use ($request, $data): int {
            $rows = $this->matching($data)->withCount('attempts')->orderBy('id')->lockForUpdate()->get();
            if (! hash_equals($this->token($rows, $data), $request->string('review_token')->toString())) {
                throw ValidationException::withMessages(['review_token' => 'Assignments changed since your review. Review the schedule again before saving.']);
            }
            $eligible = $rows->filter(fn ($row) => $row->status === 'assigned' && $row->attempts_count === 0);
            if ($eligible->isEmpty()) {
                throw ValidationException::withMessages(['assessment_id' => 'No unstarted assignments match this selection. Assign the assessment to employees first.']);
            }
            $available = Carbon::parse($data['available_from'], 'Asia/Manila')->utc();
            $due = Carbon::parse($data['due_date'], 'Asia/Manila')->utc();
            foreach ($eligible as $assignment) {
                $before = $assignment->only(['available_from', 'due_date']);
                $assignment->update(['available_from' => $available, 'due_date' => $due]);
                DB::table('assessment_activity_logs')->insert([
                    'actor_id' => $request->user()->id,
                    'action' => 'Assessment Assignment Scheduled',
                    'target_type' => AssessmentAssignment::class,
                    'target_id' => $assignment->id,
                    'metadata' => json_encode(['before' => $before, 'available_from' => $available, 'due_date' => $due], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }

            return $eligible->count();
        });

        return back()->with('status', "Schedule saved for {$count} assignment(s).");
    }
}
