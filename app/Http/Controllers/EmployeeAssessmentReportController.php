<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeAssessmentReportController extends Controller
{
    public function history(Request $request): Response
    {
        $query = AssessmentAttempt::query()->where('employee_id', $request->user()->id)->whereNotNull('submitted_at')->with(['assessment.category:id,name'])->latest('submitted_at');
        $query->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->integer('category'), fn ($q, $category) => $q->whereHas('assessment', fn ($a) => $a->where('category_id', $category)))
            ->when($request->date('from'), fn ($q, $date) => $q->whereDate('submitted_at', '>=', $date))
            ->when($request->date('to'), fn ($q, $date) => $q->whereDate('submitted_at', '<=', $date));
        $attempts = $query->paginate(15)->withQueryString()->through(fn ($attempt) => ['id' => $attempt->id, 'completed_at' => $attempt->submitted_at?->toIso8601String(), 'assessment' => $attempt->assessment->title, 'category' => $attempt->assessment->category?->name, 'attempt_number' => $attempt->attempt_number, 'percentage' => (float) $attempt->percentage, 'status' => $attempt->status, 'time_taken_seconds' => $attempt->time_taken_seconds]);

        return Inertia::render('assessments/history', ['attempts' => $attempts, 'filters' => $request->only(['status', 'category', 'from', 'to'])]);
    }

    public function progress(Request $request): Response
    {
        $finalized = AssessmentAttempt::query()->where('employee_id', $request->user()->id)->whereIn('status', ['passed', 'failed'])->whereNotNull('percentage')
            ->whereRaw('assessment_attempts.id = (select a2.id from assessment_attempts a2 where a2.employee_id = assessment_attempts.employee_id and a2.assessment_id = assessment_attempts.assessment_id and a2.status in (?, ?) order by a2.percentage desc, a2.id desc limit 1)', ['passed', 'failed'])
            ->with(['assessment:id,title', 'skillResults.skill:id,name'])->get();
        $count = $finalized->count();
        $passed = $finalized->where('passed', true)->count();
        $skills = $finalized->flatMap(fn ($attempt) => $attempt->skillResults)->groupBy('skill_id')->map(function ($rows) {
            $possible = $rows->sum(fn ($row) => (float) $row->points_possible);
            $earned = $rows->sum(fn ($row) => (float) $row->points_earned);

            return ['name' => $rows->first()->skill->name, 'earned' => $earned, 'possible' => $possible, 'percentage' => $possible ? round($earned / $possible * 100, 2) : 0];
        })->values();
        $trend = AssessmentAttempt::query()->where('employee_id', $request->user()->id)->whereIn('status', ['passed', 'failed'])->with('assessment:id,title')->oldest('submitted_at')->get()->map(fn ($attempt) => ['assessment' => $attempt->assessment->title, 'date' => $attempt->submitted_at?->toDateString(), 'percentage' => (float) $attempt->percentage]);

        return Inertia::render('assessments/progress', ['summary' => ['completed' => $count, 'average_score' => $count ? round($finalized->avg('percentage'), 2) : 0, 'pass_rate' => $count ? round($passed / $count * 100, 2) : 0, 'highest_score' => $count ? (float) $finalized->max('percentage') : 0, 'pending_review' => AssessmentAttempt::query()->where('employee_id', $request->user()->id)->where('status', 'pending_review')->count()], 'skills' => $skills, 'trend' => $trend, 'rule' => 'Summary and skills use the best finalized attempt per assessment. Pending reviews are excluded.']);
    }
}
