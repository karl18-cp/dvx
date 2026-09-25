<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class CallEvaluationEmployeeTeams
{
    public function forEmployee(User $employee): Collection
    {
        $employee->loadMissing(['teamMembership.team.campaign', 'ledTeamAssignments.team.campaign']);
        $teams = collect([$employee->teamMembership?->team]);
        if ($employee->role === 'team_leader') {
            $teams = $teams->merge($employee->ledTeamAssignments->pluck('team'));
        }

        return $teams->filter(fn ($team) => $team?->campaign !== null)->unique('id')->values();
    }
}
