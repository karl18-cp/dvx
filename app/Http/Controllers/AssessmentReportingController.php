<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use App\Models\Team;
use App\Services\AssessmentGradingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentReportingController extends Controller
{
    public function pending(Request $request): Response
    {
        $attempts = AssessmentAttempt::query()->where('status', 'pending_review')->with(['employee:id,name,username', 'assessment:id,title'])
            ->withCount(['answers as pending_count' => fn ($q) => $q->where('requires_manual_review', true)->whereNull('graded_at')])
            ->withSum(['answers as objective_points' => fn ($q) => $q->where('requires_manual_review', false)], 'points_awarded')->latest('submitted_at')->paginate(15)
            ->through(fn ($attempt) => ['id' => $attempt->id, 'employee' => $attempt->employee->name, 'employee_id' => $attempt->employee->username ?? $attempt->employee_id, 'assessment' => $attempt->assessment->title, 'attempt_number' => $attempt->attempt_number, 'submitted_at' => $attempt->submitted_at?->toIso8601String(), 'objective_points' => (float) $attempt->objective_points, 'pending_count' => $attempt->pending_count]);

        return Inertia::render('assessments/pending-reviews', ['attempts' => $attempts]);
    }

    public function grade(AssessmentAttempt $attempt): Response|RedirectResponse
    {
        if (in_array($attempt->status, ['passed', 'failed'], true)) {
            return to_route('assessments.reports.pending')->with('status', 'This grading has already been finalized. You can view the score in Results & Analytics.');
        }

        abort_unless($attempt->status === 'pending_review', 422);
        $attempt->load(['employee:id,name,username', 'assessment:id,title', 'answers']);

        return Inertia::render('assessments/grade', ['attempt' => ['id' => $attempt->id, 'employee' => $attempt->employee->name, 'assessment' => $attempt->assessment->title, 'attempt_number' => $attempt->attempt_number, 'answers' => $attempt->answers->where('requires_manual_review', true)->values()->map(fn ($answer) => ['id' => $answer->id, 'question' => $answer->question_snapshot['text'], 'skill' => $answer->question_snapshot['skill_name'], 'maximum_points' => $answer->question_snapshot['points'], 'response' => $answer->answer['text'] ?? '', 'points_awarded' => $answer->points_awarded === null ? null : (float) $answer->points_awarded, 'grader_feedback' => $answer->grader_feedback])]]);
    }

    public function saveGrade(Request $request, AssessmentAttempt $attempt, AssessmentGradingService $service): RedirectResponse
    {
        $data = $request->validate(['grades' => ['required', 'array'], 'grades.*.answer_id' => ['required', 'integer', 'distinct'], 'grades.*.points_awarded' => ['required', 'numeric', 'min:0'], 'grades.*.grader_feedback' => ['nullable', 'string', 'max:10000'], 'finalize' => ['required', 'boolean']]);
        $service->save($attempt, $request->user(), $data['grades'], $data['finalize']);

        return $data['finalize'] ? redirect()->route('assessments.reports.pending')->with('status', 'Grading finalized.') : back()->with('status', 'Grading progress saved.');
    }

    public function results(Request $request): Response
    {
        $query = AssessmentAttempt::query()->with(['employee:id,name,username', 'assessment:id,title', 'assignment.team:id,name'])->latest('submitted_at');
        $this->applyFilters($query, $request);
        $rows = $query->paginate(20)->withQueryString()->through(fn ($attempt) => ['id' => $attempt->id, 'employee' => $attempt->employee->name, 'employee_user_id' => $attempt->employee_id, 'employee_code' => $attempt->employee->username ?? $attempt->employee_id, 'team' => $attempt->assignment->team?->name, 'assessment_id' => $attempt->assessment_id, 'assessment' => $attempt->assessment->title, 'attempt_number' => $attempt->attempt_number, 'percentage' => $attempt->percentage === null ? null : (float) $attempt->percentage, 'status' => $attempt->status, 'assigned_at' => $attempt->assignment->assigned_at?->toIso8601String(), 'submitted_at' => $attempt->submitted_at?->toIso8601String(), 'time_taken_seconds' => $attempt->time_taken_seconds]);
        $assignments = AssessmentAssignment::query();
        $attempts = AssessmentAttempt::query();
        $finalized = AssessmentAttempt::query()->whereIn('status', ['passed', 'failed']);
        $totalAssigned = $assignments->count();
        $completed = (clone $attempts)->whereNotNull('submitted_at')->count();
        $finalCount = (clone $finalized)->count();

        return Inertia::render('assessments/results-analytics', ['rows' => $rows, 'summary' => ['total_assigned' => $totalAssigned, 'not_started' => AssessmentAssignment::query()->doesntHave('attempts')->count(), 'in_progress' => (clone $attempts)->where('status', 'in_progress')->count(), 'completed' => $completed, 'pending_review' => (clone $attempts)->where('status', 'pending_review')->count(), 'passed' => (clone $attempts)->where('status', 'passed')->count(), 'failed' => (clone $attempts)->where('status', 'failed')->count(), 'overdue' => AssessmentAssignment::query()->where('due_date', '<', now())->whereNotIn('status', ['passed'])->count(), 'average_score' => round((float) (clone $finalized)->avg('percentage'), 2), 'completion_rate' => $totalAssigned ? round($completed / $totalAssigned * 100, 2) : 0, 'pass_rate' => $finalCount ? round((clone $finalized)->where('status', 'passed')->count() / $finalCount * 100, 2) : 0], 'filters' => $request->query(), 'assessments' => Assessment::query()->orderBy('title')->get(['id', 'title']), 'teams' => Team::query()->orderBy('name')->get(['id', 'name']), 'categories' => AssessmentCategory::query()->orderBy('name')->get(['id', 'name']), 'skills' => AssessmentSkill::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = AssessmentAttempt::query()->select(['id', 'assessment_id', 'employee_id', 'campaign_id', 'campaign_name', 'assignment_id', 'attempt_number', 'percentage', 'status', 'submitted_at', 'time_taken_seconds'])
            ->with(['employee:id,name,username', 'assessment:id,title,category_id,passing_score', 'assessment.category:id,name', 'assignment:id,source_team_id,assigned_at', 'assignment.team:id,name'])
            ->latest('submitted_at');
        $this->applyFilters($query, $request);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Employee ID', 'Employee Name', 'Campaign', 'Team', 'Assessment', 'Category', 'Attempt Number', 'Score', 'Passing Score', 'Status', 'Assigned Date', 'Submitted Date', 'Time Taken']);
            $query->chunkById(500, function ($attempts) use ($out): void {
                foreach ($attempts as $attempt) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [$attempt->employee->username ?? $attempt->employee_id, $attempt->employee->name, $attempt->campaign_name, $attempt->assignment->team?->name, $attempt->assessment->title, $attempt->assessment->category?->name, $attempt->attempt_number, $attempt->percentage, $attempt->assessment->passing_score, $attempt->status, $attempt->assignment->assigned_at?->toIso8601String(), $attempt->submitted_at?->toIso8601String(), $attempt->time_taken_seconds]));
                }
            }, 'assessment_attempts.id', 'id');
            fclose($out);
        }, 'assessment-results-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function csvSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    public function assessment(Request $request, Assessment $assessment): Response
    {
        $assignments = $assessment->assignments();
        $best = $this->bestFinalized()->where('assessment_id', $assessment->id)->get();
        $assigned = $assignments->count();
        $completed = $best->count();
        $passed = $best->where('passed', true)->count();
        $questionStats = [];
        $assessment->attempts()->whereNotNull('submitted_at')->select(['id', 'question_snapshot'])->with(['answers:id,attempt_id,question_id,answer,points_awarded,is_correct'])->chunkById(200, function ($attempts) use (&$questionStats): void {
            foreach ($attempts as $attempt) {
                foreach ($attempt->question_snapshot ?? [] as $question) {
                    $key = $question['id'].'|'.$question['text'];
                    $answer = $attempt->answers->firstWhere('question_id', $question['id']);
                    $questionStats[$key] ??= ['question' => $question['text'], 'type' => $question['type'], 'skill' => $question['skill_name'], 'answered' => 0, 'correct' => 0, 'incorrect' => 0, 'unanswered' => 0, 'points' => 0.0, 'responses' => 0, 'options' => collect($question['options'])->mapWithKeys(fn ($o) => [(string) $o['id'] => ['text' => $o['text'], 'count' => 0]])->all()];
                    if (! $answer) {
                        $questionStats[$key]['unanswered']++;

                        continue;
                    }
                    $questionStats[$key]['answered']++;
                    if ($question['type'] === 'short_answer') {
                        if ($answer->points_awarded !== null) {
                            $questionStats[$key]['points'] += (float) $answer->points_awarded;
                            $questionStats[$key]['responses']++;
                        }
                    } else {
                        $questionStats[$key][$answer->is_correct ? 'correct' : 'incorrect']++;
                        foreach ($answer->answer['option_ids'] ?? [] as $id) {
                            if (isset($questionStats[$key]['options'][(string) $id])) {
                                $questionStats[$key]['options'][(string) $id]['count']++;
                            }
                        }
                    }
                }
            }
        });
        $questions = collect($questionStats)->values()->map(function ($row) {
            $row['correct_percentage'] = $row['answered'] ? round($row['correct'] / $row['answered'] * 100, 2) : 0;
            $row['incorrect_percentage'] = $row['answered'] ? round($row['incorrect'] / $row['answered'] * 100, 2) : 0;
            $row['average_points'] = $row['responses'] ? round($row['points'] / $row['responses'], 2) : null;
            $row['options'] = collect($row['options'])->map(fn ($o) => [...$o, 'percentage' => $row['answered'] ? round($o['count'] / $row['answered'] * 100, 2) : 0])->values();

            return $row;
        });

        return Inertia::render('assessments/assessment-analytics', ['assessment' => ['id' => $assessment->id, 'title' => $assessment->title], 'summary' => ['total_assigned' => $assigned, 'not_started' => $assessment->assignments()->doesntHave('attempts')->count(), 'in_progress' => $assessment->attempts()->where('status', 'in_progress')->count(), 'completed' => $completed, 'pending_review' => $assessment->attempts()->where('status', 'pending_review')->count(), 'passed' => $passed, 'failed' => $completed - $passed, 'overdue' => $assessment->assignments()->where('due_date', '<', now())->whereNotIn('status', ['passed'])->count(), 'completion_rate' => $assigned ? round($completed / $assigned * 100, 2) : 0, 'pass_rate' => $completed ? round($passed / $completed * 100, 2) : 0, 'average_score' => round((float) $best->avg('percentage'), 2), 'highest_score' => (float) ($best->max('percentage') ?? 0), 'lowest_score' => (float) ($best->min('percentage') ?? 0), 'average_time' => round((float) $best->avg('time_taken_seconds'))], 'questions' => $questions]);
    }

    public function team(Team $team): Response
    {
        $employeeIds = $team->members()->pluck('user_id');
        $assignments = AssessmentAssignment::query()->whereIn('employee_id', $employeeIds);
        $best = $this->bestFinalized()->whereIn('employee_id', $employeeIds)->with('skillResults.skill:id,name')->get();
        $assigned = (clone $assignments)->count();
        $completed = $best->count();
        $passed = $best->where('passed', true)->count();
        $skills = $best->flatMap->skillResults->groupBy('skill_id')->map(function ($rows) {
            $possible = $rows->sum(fn ($row) => (float) $row->points_possible);

            return ['name' => $rows->first()->skill->name, 'percentage' => $possible ? round($rows->sum(fn ($row) => (float) $row->points_earned) / $possible * 100, 2) : 0];
        })->values();

        return Inertia::render('assessments/team-analytics', ['team' => ['id' => $team->id, 'name' => $team->name], 'summary' => ['employees_assigned' => (clone $assignments)->distinct()->count('employee_id'), 'assessments_completed' => $completed, 'completion_rate' => $assigned ? round($completed / $assigned * 100, 2) : 0, 'average_score' => round((float) $best->avg('percentage'), 2), 'pass_rate' => $completed ? round($passed / $completed * 100, 2) : 0, 'pending_review' => AssessmentAttempt::query()->whereIn('employee_id', $employeeIds)->where('status', 'pending_review')->count(), 'overdue' => (clone $assignments)->where('due_date', '<', now())->whereNotIn('status', ['passed'])->count()], 'skills' => $skills]);
    }

    private function bestFinalized(): Builder
    {
        return AssessmentAttempt::query()->whereIn('status', ['passed', 'failed'])->whereRaw('assessment_attempts.id = (select a2.id from assessment_attempts a2 where a2.employee_id = assessment_attempts.employee_id and a2.assessment_id = assessment_attempts.assessment_id and a2.status in (?, ?) order by a2.percentage desc, a2.id desc limit 1)', ['passed', 'failed']);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('campaign'), fn ($q, $id) => $q->where('campaign_id', $id));
        $query->when($request->integer('assessment'), fn ($q, $id) => $q->where('assessment_id', $id))->when($request->integer('employee'), fn ($q, $id) => $q->where('employee_id', $id))->when($request->integer('team'), fn ($q, $id) => $q->whereHas('assignment', fn ($a) => $a->where('source_team_id', $id)))->when($request->integer('category'), fn ($q, $id) => $q->whereHas('assessment', fn ($a) => $a->where('category_id', $id)))->when($request->integer('skill'), fn ($q, $id) => $q->whereHas('skillResults', fn ($s) => $s->where('skill_id', $id)))->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))->when($request->date('from'), fn ($q, $date) => $q->whereDate('submitted_at', '>=', $date))->when($request->date('to'), fn ($q, $date) => $q->whereDate('submitted_at', '<=', $date));
    }
}
