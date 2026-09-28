<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class QaAccessService
{
    public function teamIds(User $user): array
    {
        if (in_array($user->role, ['admin', 'manager', 'qa_admin'], true)) {
            return [];
        }

        return $user->ledTeamAssignments()->pluck('team_id')->map(fn ($id) => (int) $id)->all();
    }

    public function scope(Builder $query, User $user, string $column = 'team_id'): Builder
    {
        if ($user->role === 'team_leader') {
            $query->where(fn ($q) => $q->whereIn($column, $this->teamIds($user))->orWhere(fn ($unassigned) => $unassigned->whereNull($column)->whereIn($query->getModel()->qualifyColumn('employee_id'), app(TeamLeaderWorkspaceService::class)->members($user)->where('role', 'trainee')->select('users.id'))));
            $query->whereIn($query->getModel()->qualifyColumn('employee_id'), app(TeamLeaderWorkspaceService::class)->members($user)->select('users.id'));
        }

        return $query;
    }

    public function authorizeTeam(User $user, ?int $teamId, ?int $employeeId = null): void
    {
        if ($user->role === 'team_leader') {
            if ($teamId === null && $employeeId !== null && app(TeamLeaderWorkspaceService::class)->members($user)->where('role', 'trainee')->whereKey($employeeId)->exists()) {
                return;
            }
            abort_unless($teamId && in_array($teamId, $this->teamIds($user), true), 403);
            if ($employeeId !== null) {
                abort_unless(app(TeamLeaderWorkspaceService::class)->members($user)->whereKey($employeeId)->exists(), 403);
            }
        }
    }
}
