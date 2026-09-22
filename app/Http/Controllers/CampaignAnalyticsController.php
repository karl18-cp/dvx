<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CampaignAnalyticsController extends Controller
{
    public function index(): Response
    {
        $campaigns = Campaign::query()->with('teams:id,campaign_id,name')->withCount(['teams as employees_count' => fn ($q) => $q->join('team_members', 'team_members.team_id', '=', 'teams.id')])->orderByDesc('is_active')->orderBy('name')->get();
        $assignments = DB::table('assessment_assignments')->selectRaw('campaign_id, count(*) assigned, sum(case when status in ("passed","failed") then 1 else 0 end) completed, sum(case when status = "pending_review" then 1 else 0 end) pending_review, sum(case when due_date < ? and status not in ("passed","failed") then 1 else 0 end) overdue', [now()])->whereNotNull('campaign_id')->groupBy('campaign_id')->get()->keyBy('campaign_id');
        $results = DB::table('assessment_attempts')->selectRaw('campaign_id, avg(case when status in ("passed","failed") then percentage end) average_score, avg(case when status in ("passed","failed") then passed end) * 100 pass_rate')->whereNotNull('campaign_id')->groupBy('campaign_id')->get()->keyBy('campaign_id');

        return Inertia::render('assessments/campaign-analytics', ['campaigns' => $campaigns->map(function ($campaign) use ($assignments, $results) {
            $a = $assignments->get($campaign->id);
            $r = $results->get($campaign->id);
            $assigned = (int) ($a->assigned ?? 0);
            $completed = (int) ($a->completed ?? 0);

            return ['id' => $campaign->id, 'name' => $campaign->name, 'abbreviation' => $campaign->abbreviation, 'status' => $campaign->is_active ? 'Active' : 'Inactive', 'employees' => $campaign->employees_count, 'assigned' => $assigned, 'completed' => $completed, 'pending_review' => (int) ($a->pending_review ?? 0), 'overdue' => (int) ($a->overdue ?? 0), 'completion_rate' => $assigned ? round($completed / $assigned * 100, 2) : 0, 'average_score' => round((float) ($r->average_score ?? 0), 2), 'pass_rate' => round((float) ($r->pass_rate ?? 0), 2), 'teams' => $campaign->teams];
        })]);
    }
}
