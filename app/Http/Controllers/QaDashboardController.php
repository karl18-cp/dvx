<?php

namespace App\Http\Controllers;

use App\Models\CallEvaluation;
use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\QaAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QaDashboardController extends Controller
{
    private const TIMEZONE = 'Asia/Manila';

    public function index(Request $request, QaAccessService $access): Response
    {
        [$filters, $from, $to] = $this->filters($request);
        $base = $this->evaluations($request, $access, $filters, $from, $to);
        $summary = (clone $base)->selectRaw("count(*) as calls_evaluated, coalesce(avg(percentage), 0) as average_score, sum(case when result = 'passed' then 1 else 0 end) as passed, sum(case when result in ('failed', 'failed_critical') then 1 else 0 end) as failed")->first();
        $performance = (clone $base)->selectRaw('employee_id, campaign_id, campaign_name, team_id, team_name, count(*) as evaluations, avg(percentage) as average_score, sum(case when result = ? then 1 else 0 end) as passed, sum(case when result in (?, ?) then 1 else 0 end) as failed', ['passed', 'failed', 'failed_critical'])
            ->groupBy('employee_id', 'campaign_id', 'campaign_name', 'team_id', 'team_name')->orderByDesc('average_score')->paginate(15)->withQueryString();
        $keys = collect($performance->items())->map(fn ($row) => $row->employee_id.'|'.$row->campaign_id.'|'.$row->team_id);
        $employees = User::query()->whereIn('id', collect($performance->items())->pluck('employee_id'))->get(['id', 'name', 'username'])->keyBy('id');
        $latest = (clone $base)->whereIn('employee_id', collect($performance->items())->pluck('employee_id'))->orderByDesc('call_at')->orderByDesc('id')->get(['id', 'employee_id', 'campaign_id', 'team_id', 'percentage', 'result', 'has_critical_failure'])->unique(fn ($row) => $row->employee_id.'|'.$row->campaign_id.'|'.$row->team_id)->keyBy(fn ($row) => $row->employee_id.'|'.$row->campaign_id.'|'.$row->team_id);
        $coaching = $access->scope(CoachingRecord::query()->whereIn('employee_id', collect($performance->items())->pluck('employee_id')), $request->user())->get(['employee_id', 'campaign_id', 'team_id', 'status'])->groupBy(fn ($row) => $row->employee_id.'|'.$row->campaign_id.'|'.$row->team_id);
        $performance->through(function ($row) use ($employees, $latest, $coaching) {
            $key = $row->employee_id.'|'.$row->campaign_id.'|'.$row->team_id;
            $sessions = $coaching->get($key, collect());
            $open = $sessions->where('status', '!=', 'completed')->count();

            return ['employee_id' => $row->employee_id, 'employee' => $employees->get($row->employee_id)?->only(['id', 'name', 'username']), 'campaign_id' => $row->campaign_id, 'campaign' => $row->campaign_name, 'team_id' => $row->team_id, 'team' => $row->team_name, 'evaluations' => (int) $row->evaluations, 'average_score' => round((float) $row->average_score, 2), 'latest_score' => (float) ($latest->get($key)?->percentage ?? 0), 'passed' => (int) $row->passed, 'failed' => (int) $row->failed, 'open_coaching' => $open, 'coaching_status' => $open ? 'Open' : ($sessions->isNotEmpty() ? 'Completed' : 'Not Created')];
        });
        $openCoaching = $this->coaching($request, $access, $filters, $from, $to)->where('status', '!=', 'completed')->count();

        return Inertia::render('quality/dashboard', [
            'summary' => ['calls_evaluated' => (int) $summary->calls_evaluated, 'average_score' => round((float) $summary->average_score, 2), 'passed' => (int) $summary->passed, 'failed' => (int) $summary->failed, 'open_coaching' => $openCoaching],
            'performance' => $performance,
            'filters' => $filters,
            'campaigns' => Campaign::query()->when($request->user()->role === 'team_leader', fn ($q) => $q->whereHas('teams', fn ($teams) => $teams->whereIn('teams.id', $access->teamIds($request->user()))))->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->when($request->user()->role === 'team_leader', fn ($q) => $q->whereIn('id', $access->teamIds($request->user())))->orderBy('name')->get(['id', 'name', 'campaign_id']),
            'employees' => User::query()->whereIn('role', ['agent', 'team_leader'])->when($request->user()->role === 'team_leader', fn ($q) => $q->whereHas('teamMembership', fn ($m) => $m->whereIn('team_id', $access->teamIds($request->user()))))->orderBy('name')->get(['id', 'name', 'username']),
        ]);
    }

    public function employee(Request $request, User $employee, QaAccessService $access): Response
    {
        $filters = $request->only(['campaign', 'team']);
        $base = $access->scope(CallEvaluation::query()->where('status', 'submitted')->where('employee_id', $employee->id), $request->user());
        $base->when($filters['campaign'] ?? null, fn ($q, $id) => $q->where('campaign_id', $id))->when($filters['team'] ?? null, fn ($q, $id) => $q->where('team_id', $id));
        abort_unless((clone $base)->exists(), 404);
        $summary = (clone $base)->selectRaw("count(*) as total, coalesce(avg(percentage), 0) as average, sum(case when result = 'passed' then 1 else 0 end) as passed, sum(case when result in ('failed', 'failed_critical') then 1 else 0 end) as failed")->first();
        $history = (clone $base)->with(['evaluator:id,name', 'coachingRecord:id,call_evaluation_id,status'])->latest('call_at')->paginate(15)->withQueryString();
        $open = $access->scope(CoachingRecord::query()->where('employee_id', $employee->id)->where('status', '!=', 'completed'), $request->user())->count();

        return Inertia::render('quality/employee-history', ['employee' => $employee->load('teamMembership.team.campaign:id,name')->only(['id', 'name', 'username', 'team_membership']), 'summary' => ['average_score' => round((float) $summary->average, 2), 'total' => (int) $summary->total, 'passed' => (int) $summary->passed, 'failed' => (int) $summary->failed, 'open_coaching' => $open], 'history' => $history]);
    }

    public function export(Request $request, QaAccessService $access): StreamedResponse
    {
        [$filters, $from, $to] = $this->filters($request);
        $rows = $this->evaluations($request, $access, $filters, $from, $to)->join('users', 'users.id', '=', 'call_evaluations.employee_id')->selectRaw("users.username employee_code, users.name employee, call_evaluations.campaign_name, call_evaluations.team_name, count(*) evaluations, avg(percentage) average_score, sum(case when result = 'passed' then 1 else 0 end) passed, sum(case when result in ('failed', 'failed_critical') then 1 else 0 end) failed")->groupBy('users.username', 'users.name', 'call_evaluations.campaign_name', 'call_evaluations.team_name')->orderBy('users.name')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee ID', 'Employee', 'Campaign', 'Team', 'Evaluations', 'Average QA Score', 'Passed', 'Failed']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->employee_code, $row->employee, $row->campaign_name, $row->team_name, $row->evaluations, round((float) $row->average_score, 2), $row->passed, $row->failed]);
            }
            fclose($out);
        }, 'qa-performance.csv', ['Content-Type' => 'text/csv']);
    }

    private function evaluations(Request $request, QaAccessService $access, array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $access->scope(CallEvaluation::query()->where('call_evaluations.status', 'submitted')->whereBetween('call_evaluations.call_at', [$from, $to]), $request->user(), 'call_evaluations.team_id')
            ->when($filters['campaign'] ?? null, fn ($q, $id) => $q->where('call_evaluations.campaign_id', $id))->when($filters['team'] ?? null, fn ($q, $id) => $q->where('call_evaluations.team_id', $id))->when($filters['employee'] ?? null, fn ($q, $id) => $q->where('call_evaluations.employee_id', $id));
    }

    private function coaching(Request $request, QaAccessService $access, array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $access->scope(CoachingRecord::query()->whereBetween('coaching_date', [$from->toDateString(), $to->toDateString()]), $request->user())
            ->when($filters['campaign'] ?? null, fn ($q, $id) => $q->where('campaign_id', $id))->when($filters['team'] ?? null, fn ($q, $id) => $q->where('team_id', $id))->when($filters['employee'] ?? null, fn ($q, $id) => $q->where('employee_id', $id));
    }

    private function filters(Request $request): array
    {
        $filters = $request->only(['range', 'from', 'to', 'campaign', 'team', 'employee']);
        $filters['range'] = in_array($filters['range'] ?? null, ['today', 'week', 'month', 'custom'], true) ? $filters['range'] : 'month';
        $now = CarbonImmutable::now(self::TIMEZONE);
        [$from, $to] = match ($filters['range']) {
            'today' => [$now->startOfDay(), $now->endOfDay()], 'week' => [$now->startOfWeek(), $now->endOfWeek()], 'custom' => [CarbonImmutable::parse($filters['from'] ?? $now->startOfMonth(), self::TIMEZONE)->startOfDay(), CarbonImmutable::parse($filters['to'] ?? $now, self::TIMEZONE)->endOfDay()], default => [$now->startOfMonth(), $now->endOfMonth()]
        };

        return [$filters, $from->utc(), $to->utc()];
    }
}
