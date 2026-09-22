<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('team-assigning', [
            'teams' => Team::query()
                ->with(['campaign:id,name,abbreviation', 'leaderAssignment.user:id,name,username', 'members:id,team_id,user_id'])
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'campaign' => $team->campaign->name,
                    'campaignAbbreviation' => $team->campaign->abbreviation,
                    'leaderId' => $team->leaderAssignment?->user_id,
                    'memberIds' => $team->members->pluck('user_id')->values(),
                ]),
            'teamLeaders' => User::query()
                ->with('ledTeamAssignments.team:id,name')
                ->where('role', 'team_leader')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'username', 'name', 'email'])
                ->map(fn (User $leader): array => [
                    'id' => $leader->id,
                    'employeeId' => $leader->username,
                    'name' => $leader->name,
                    'email' => $leader->email,
                    'teams' => $leader->ledTeamAssignments->pluck('team.name')->filter()->values(),
                ]),
            'agents' => User::query()
                ->with('teamMembership.team.campaign:id,name,abbreviation')
                ->where('role', 'agent')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'username', 'name', 'email'])
                ->map(fn (User $agent): array => [
                    'id' => $agent->id,
                    'employeeId' => $agent->username,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'currentTeamId' => $agent->teamMembership?->team_id,
                    'currentTeam' => $agent->teamMembership?->team?->name,
                    'currentCampaign' => $agent->teamMembership?->team?->campaign?->name,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
            'team_leader_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'team_leader')],
            'agent_ids' => ['array'],
            'agent_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', 'agent')],
        ]);

        $agentIds = collect($data['agent_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $transferred = DB::transaction(function () use ($data, $agentIds): int {
            TeamLeaderAssignment::query()->updateOrCreate(
                ['team_id' => $data['team_id']],
                ['user_id' => $data['team_leader_id']],
            );

            $existingMemberships = TeamMember::query()
                ->whereIn('user_id', $agentIds)
                ->lockForUpdate()
                ->get();
            $transferCount = $existingMemberships
                ->where('team_id', '!=', $data['team_id'])
                ->count();

            TeamMember::query()
                ->where('team_id', $data['team_id'])
                ->when($agentIds->isNotEmpty(), fn ($query) => $query->whereNotIn('user_id', $agentIds))
                ->delete();

            TeamMember::query()
                ->whereIn('user_id', $agentIds)
                ->where('team_id', '!=', $data['team_id'])
                ->delete();

            foreach ($agentIds as $agentId) {
                TeamMember::query()->firstOrCreate([
                    'team_id' => $data['team_id'],
                    'user_id' => $agentId,
                ]);
            }

            return $transferCount;
        });

        $message = 'Team assignment saved successfully.';
        if ($transferred > 0) {
            $message .= " {$transferred} agent(s) transferred from their previous team.";
        }

        return to_route('team-assigning')->with('status', $message);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'agent_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'agent')->where('status', 'active'))],
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
        ]);

        $team = Team::query()->with('campaign:id,name')->findOrFail($data['team_id']);
        $agent = User::query()->findOrFail($data['agent_id']);
        $oldTeam = null;

        DB::transaction(function () use ($data, $team, $agent, $request, &$oldTeam): void {
            $membership = TeamMember::query()->where('user_id', $data['agent_id'])->lockForUpdate()->first();
            $oldTeam = $membership?->team()->with('campaign:id,name')->first();

            if ($oldTeam?->id === $team->id) {
                return;
            }

            if ($membership) {
                $membership->update(['team_id' => $team->id]);
            } else {
                TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $agent->id]);
            }

            DB::table('assessment_activity_logs')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'Employee Team Transferred',
                'target_type' => User::class,
                'target_id' => $agent->id,
                'metadata' => json_encode([
                    'from_team_id' => $oldTeam?->id,
                    'from_team_name' => $oldTeam?->name,
                    'from_campaign_id' => $oldTeam?->campaign_id,
                    'from_campaign_name' => $oldTeam?->campaign?->name,
                    'to_team_id' => $team->id,
                    'to_team_name' => $team->name,
                    'to_campaign_id' => $team->campaign_id,
                    'to_campaign_name' => $team->campaign?->name,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });

        if ($oldTeam?->id === $team->id) {
            return back()->with('status', "{$agent->name} is already assigned to {$team->name}.");
        }

        return back()->with('status', "{$agent->name} transferred to {$team->name}. Historical records were preserved.");
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}
