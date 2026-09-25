<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\TrackerTaskAssignment;
use App\Models\User;

class TeamLeaderWorkspaceService
{
    public function teams(User $leader)
    {
        return Team::whereHas('leaderAssignment', fn ($q) => $q->where('user_id', $leader->id));
    }

    public function members(User $leader)
    {
        return User::where('role', 'agent')->whereHas('teamMembership', fn ($q) => $q->whereIn('team_id', $this->teams($leader)->select('teams.id')));
    }

    public function summary(User $leader): array
    {
        $pending = LeaveRequest::whereIn('user_id', $this->members($leader)->where('role', 'agent')->where('id', '!=', $leader->id)->select('users.id'))->whereIn('status', ['needs_review', 'pending'])->where('requires_leader_approval', true)->where('leader_status', 'pending')->count();

        return ['teams' => $this->teams($leader)->count(), 'members' => $this->members($leader)->where('status', 'active')->count(), 'pendingRequests' => $pending, 'openTasks' => TrackerTaskAssignment::where('user_id', $leader->id)->whereHas('task')->where('status', '!=', 'approved_done')->count()];
    }
}
