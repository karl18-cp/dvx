<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePersonalScheduleRequest;
use App\Models\CampaignSchedule;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamLeaderWorkspaceService;
use App\Services\TraineeTeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamLeaderWorkspaceController extends Controller
{
    public function __invoke(Request $request, TeamLeaderWorkspaceService $workspace)
    {
        $leader = $request->user()->fresh();
        abort_unless($leader->role === 'team_leader' && $leader->status === 'active', 403);

        return Inertia::render('my-team', [
            'summary' => $workspace->summary($leader),
            'ownSchedule' => $leader->campaignSchedule?->load('days'),
            'assignableAgents' => User::whereIn('role', ['agent', 'trainee'])->where('status', 'active')
                ->where(fn ($q) => $q->where('role', 'agent')->orWhere(fn ($t) => $t->whereIn('training_status', ['in_training', 'graduated'])->whereIn('training_campaign_id', $workspace->teams($leader)->select('campaign_id'))))
                ->where(fn ($q) => $q->whereDoesntHave('teamMembership')->orWhereHas('teamMembership', fn ($m) => $m->whereIn('team_id', $workspace->teams($leader)->select('teams.id'))))
                ->orderBy('name')->get(['id', 'name', 'username', 'role', 'training_campaign_id']),
            'teams' => $workspace->teams($leader)->with('campaign:id,name')->orderBy('name')->get()->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'campaign' => $team->campaign?->name, 'campaign_id' => $team->campaign_id]),
            'members' => $workspace->members($leader)->with(['teamMembership.team', 'campaignSchedule.days'])->orderBy('name')->get()->map(fn ($user) => [
                'id' => $user->id, 'name' => $user->name, 'employeeId' => $user->username, 'avatar' => $user->avatar, 'role' => $user->role, 'status' => $user->status, 'teamId' => $user->teamMembership?->team_id, 'team' => $user->teamMembership?->team?->name,
                'schedule' => $user->campaignSchedule ? ['name' => $user->campaignSchedule->name, 'days' => $user->campaignSchedule->days->sortBy('day')->values()->map(fn ($day) => $day->only(['day', 'no_schedule', 'time_in', 'time_out', 'break_start', 'break_end']))] : null,
            ]),
        ]);
    }

    public function assign(Request $request, TeamLeaderWorkspaceService $workspace)
    {
        $leader = $request->user()->fresh();
        abort_unless($leader->role === 'team_leader' && $leader->status === 'active', 403);
        $data = $request->validate(['team_id' => ['required', 'integer'], 'agent_id' => ['required', 'integer']]);
        DB::transaction(function () use ($leader, $data, $workspace): void {
            abort_unless($workspace->teams($leader)->whereKey($data['team_id'])->exists(), 403);
            $agent = User::whereKey($data['agent_id'])->lockForUpdate()->firstOrFail();
            abort_unless(in_array($agent->role, ['agent', 'trainee'], true) && $agent->status === 'active', 403);
            $membership = TeamMember::where('user_id', $agent->id)->lockForUpdate()->first();
            abort_if($membership && ! $workspace->teams($leader)->whereKey($membership->team_id)->exists(), 403);
            app(TraineeTeamService::class)->validateTeam($agent, Team::findOrFail($data['team_id']));
            $before = $membership?->team_id;
            TeamMember::updateOrCreate(['user_id' => $agent->id], ['team_id' => $data['team_id']]);
            $this->audit($leader, $agent, 'Team leader assigned member', ['from_team_id' => $before, 'to_team_id' => $data['team_id']]);
        });

        return back()->with('status', 'Team member assigned to your team.');
    }

    public function schedule(SavePersonalScheduleRequest $request)
    {
        DB::transaction(function () use ($request): void {
            $leader = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $schedule = CampaignSchedule::whereKey($leader->campaign_schedule_id)->lockForUpdate()->first();
            abort_unless($schedule, 422, 'Ask an admin to assign your campaign schedule first.');
            $before = $schedule->load('days')->toArray();
            // Shared templates must never change other employees when a leader edits their own hours.
            if ($schedule->employees()->where('id', '!=', $leader->id)->exists()) {
                $schedule = CampaignSchedule::create(['name' => 'Personal schedule '.($leader->username ?: $leader->id).' '.Str::lower(Str::random(6)), 'created_by' => $leader->id]);
                $leader->forceFill(['campaign_schedule_id' => $schedule->id])->save();
            }
            $schedule->days()->delete();
            $schedule->days()->createMany($request->scheduleDays());
            $this->audit($leader, $leader, 'Team leader updated personal schedule', ['before' => $before, 'after' => $schedule->fresh()->load('days')->toArray()]);
        });

        return back()->with('status', 'Your schedule was updated. Existing attendance snapshots are preserved.');
    }

    private function audit(User $actor, User $target, string $action, array $metadata): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $actor->id, 'action' => $action, 'target_type' => User::class, 'target_id' => $target->id, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
