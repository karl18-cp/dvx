<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttempt;
use App\Models\CallEvaluation;
use App\Models\CoachingRecord;
use App\Models\User;
use App\Services\QaAccessService;
use App\Support\SkillPerformanceStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeTrainingProfileController extends Controller
{
    public function show(Request $request, User $employee, QaAccessService $access): Response
    {
        abort_unless(in_array($employee->role, ['agent', 'team_leader'], true), 404);
        $teamId = $employee->teamMembership?->team_id;
        $access->authorizeTeam($request->user(), $teamId);

        $bestAttempts = AssessmentAttempt::query()->where('employee_id', $employee->id)->whereIn('status', ['passed', 'failed'])->whereNotNull('percentage')
            ->whereRaw('assessment_attempts.id = (select a2.id from assessment_attempts a2 where a2.employee_id = assessment_attempts.employee_id and a2.assessment_id = assessment_attempts.assessment_id and a2.status in (?, ?) order by a2.percentage desc, a2.id desc limit 1)', ['passed', 'failed']);
        $completed = (clone $bestAttempts)->count();
        $passed = (clone $bestAttempts)->where('passed', true)->count();
        $skills = DB::table('assessment_skill_results as sr')->join('assessment_attempts as a', 'a.id', '=', 'sr.attempt_id')->join('assessment_skills as s', 's.id', '=', 'sr.skill_id')
            ->where('a.employee_id', $employee->id)->whereIn('a.status', ['passed', 'failed'])
            ->whereRaw('a.id = (select a2.id from assessment_attempts a2 where a2.employee_id = a.employee_id and a2.assessment_id = a.assessment_id and a2.status in (?, ?) order by a2.percentage desc, a2.id desc limit 1)', ['passed', 'failed'])
            ->groupBy('s.id', 's.name')->selectRaw('s.id, s.name, sum(sr.points_earned) earned, sum(sr.points_possible) possible')->get()
            ->map(function ($skill) {
                $percentage = $skill->possible > 0 ? round($skill->earned / $skill->possible * 100, 2) : 0;

                return ['id' => $skill->id, 'name' => $skill->name, 'percentage' => $percentage, 'status' => SkillPerformanceStatus::label($percentage)];
            })->sortByDesc('percentage')->values();

        $recentAssessments = AssessmentAttempt::query()->where('employee_id', $employee->id)->whereNotNull('submitted_at')->with('assessment.category:id,name')->latest('submitted_at')->limit(8)->get()->map(fn ($attempt) => ['id' => $attempt->id, 'assessment' => $attempt->assessment->title, 'category' => $attempt->assessment->category?->name, 'date' => $attempt->submitted_at, 'score' => (float) $attempt->percentage, 'status' => $attempt->status, 'attempt' => $attempt->attempt_number]);

        $directTraining = DB::table('assessment_training_progress as p')->join('assessment_training_materials as m', 'm.id', '=', 'p.material_id')->join('assessments as a', 'a.id', '=', 'm.assessment_id')->where('p.employee_id', $employee->id)->whereNotNull('p.completed_at')->select('m.title', 'm.type', DB::raw('NULL as skill'), 'a.title as assessment', 'p.completed_at')->orderByDesc('p.completed_at')->limit(8)->get();
        $assessmentTraining = DB::table('assessment_training_attachment_progress as p')->join('assessment_training_attachments as ata', 'ata.id', '=', 'p.attachment_id')->join('training_library_materials as m', 'm.id', '=', 'ata.library_material_id')->join('assessments as a', 'a.id', '=', 'ata.assessment_id')->leftJoin('assessment_skills as s', 's.id', '=', 'm.skill_id')->where('p.employee_id', $employee->id)->whereNotNull('p.completed_at')->select('m.title', 'm.type', 's.name as skill', 'a.title as assessment', 'p.completed_at')->orderByDesc('p.completed_at')->limit(8)->get();
        $coachingTraining = DB::table('coaching_training_progress as p')->join('coaching_training_assignments as cta', 'cta.id', '=', 'p.coaching_training_assignment_id')->join('coaching_records as c', 'c.id', '=', 'cta.coaching_record_id')->join('training_library_materials as m', 'm.id', '=', 'cta.library_material_id')->leftJoin('assessment_skills as s', 's.id', '=', 'm.skill_id')->where('p.employee_id', $employee->id)->whereNotNull('p.completed_at')->select('m.title', 'm.type', 's.name as skill', DB::raw("'Coaching' as assessment"), 'p.completed_at')->orderByDesc('p.completed_at')->limit(8)->get();
        $recentTraining = $directTraining->concat($assessmentTraining)->concat($coachingTraining)->sortByDesc('completed_at')->take(8)->values();
        $recentCoaching = CoachingRecord::query()->where('employee_id', $employee->id)->with(['coach:id,name', 'skill:id,name'])->latest('coaching_date')->limit(6)->get();
        $qaEvaluations = CallEvaluation::query()->where('employee_id', $employee->id)->where('status', 'submitted')->with('evaluator:id,name')->latest('finalized_at')->limit(8)->get(['id', 'employee_id', 'evaluator_id', 'scorecard_name', 'campaign_name', 'call_at', 'percentage', 'result', 'finalized_at']);
        $qaSkillTotals = [];
        CallEvaluation::query()->where('employee_id', $employee->id)->where('status', 'submitted')->with('criterionResults')->get()->each(function ($evaluation) use (&$qaSkillTotals): void {
            $results = $evaluation->criterionResults->keyBy('criterion_snapshot_key');
            foreach ($evaluation->scorecard_snapshot['categories'] ?? [] as $category) {
                foreach ($category['criteria'] ?? [] as $criterion) {
                    $result = $results->get($criterion['key']);
                    if (! ($criterion['skill_id'] ?? null) || ! $result || $result->is_na || $result->points_awarded === null) {
                        continue;
                    }
                    $key = (string) $criterion['skill_id'];
                    $qaSkillTotals[$key] ??= ['id' => $criterion['skill_id'], 'name' => $criterion['skill_name'], 'earned' => 0.0, 'possible' => 0.0];
                    $qaSkillTotals[$key]['earned'] += (float) $result->points_awarded;
                    $qaSkillTotals[$key]['possible'] += (float) $criterion['points_possible'];
                }
            }
        });
        $qaSkills = collect($qaSkillTotals)->map(fn ($skill) => [...$skill, 'percentage' => $skill['possible'] > 0 ? round($skill['earned'] / $skill['possible'] * 100, 2) : 0])->values();
        $trainingCompleted = DB::table('assessment_training_progress')->where('employee_id', $employee->id)->whereNotNull('completed_at')->count()
            + DB::table('assessment_training_attachment_progress')->where('employee_id', $employee->id)->whereNotNull('completed_at')->count()
            + DB::table('coaching_training_progress')->where('employee_id', $employee->id)->whereNotNull('completed_at')->count();

        return Inertia::render('coaching/profile', [
            'employee' => $employee->load('teamMembership.team.campaign:id,name,abbreviation')->only(['id', 'name', 'username', 'team_membership']),
            'summary' => ['assessments_completed' => $completed, 'average_score' => $completed ? round((float) (clone $bestAttempts)->avg('percentage'), 2) : 0, 'pass_rate' => $completed ? round($passed / $completed * 100, 2) : 0, 'training_completed' => $trainingCompleted, 'coaching_sessions' => CoachingRecord::query()->where('employee_id', $employee->id)->count(), 'pending_actions' => CoachingRecord::query()->where('employee_id', $employee->id)->whereNot('status', 'completed')->count()],
            'skills' => $skills,
            'recentAssessments' => $recentAssessments,
            'recentTraining' => $recentTraining,
            'recentCoaching' => $recentCoaching,
            'qaEvaluations' => $qaEvaluations,
            'qaSkills' => $qaSkills,
        ]);
    }
}
