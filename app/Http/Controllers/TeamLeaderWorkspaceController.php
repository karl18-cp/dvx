<?php

namespace App\Http\Controllers;

use App\Services\TeamLeaderWorkspaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamLeaderWorkspaceController extends Controller
{
    public function __invoke(Request $request, TeamLeaderWorkspaceService $workspace)
    {
        $leader = $request->user()->fresh();
        abort_unless($leader->role === 'team_leader' && $leader->status === 'active', 403);

        return Inertia::render('my-team', [
            'summary' => $workspace->summary($leader),
            'teams' => $workspace->teams($leader)->with('campaign:id,name')->orderBy('name')->get()->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'campaign' => $team->campaign?->name]),
            'members' => $workspace->members($leader)->with(['teamMembership.team', 'campaignSchedule.days'])->orderBy('name')->get()->map(fn ($user) => [
                'id' => $user->id, 'name' => $user->name, 'employeeId' => $user->username, 'avatar' => $user->avatar, 'role' => $user->role, 'status' => $user->status, 'teamId' => $user->teamMembership?->team_id, 'team' => $user->teamMembership?->team?->name,
                'schedule' => $user->campaignSchedule ? ['name' => $user->campaignSchedule->name, 'days' => $user->campaignSchedule->days->sortBy('day')->values()->map(fn ($day) => $day->only(['day', 'no_schedule', 'time_in', 'time_out', 'break_start', 'break_end']))] : null,
            ]),
        ]);
    }
}
