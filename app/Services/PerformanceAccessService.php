<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PerformanceAccessService
{
    public function canManage(User $user): bool
    {
        return app(RolePermissionService::class)->can($user, 'performance.manage');
    }

    public function visibleEmployees(User $actor): Builder
    {
        $query = User::query()->whereNotNull('username');
        if (! $this->canManage($actor)) {
            return $query->whereKey($actor->id);
        }
        if ($actor->role === 'team_leader') {
            return $query->where(fn ($users) => $users->whereKey($actor->id)->orWhereIn('id', app(TeamLeaderWorkspaceService::class)->members($actor)->select('users.id')));
        }

        return $query;
    }

    public function authorizeView(User $actor, User $employee): void
    {
        abort_unless($actor->is($employee) || $this->canManageEmployee($actor, $employee), 403);
    }

    public function authorizeManage(User $actor, User $employee): void
    {
        abort_unless($this->canManageEmployee($actor, $employee), 403);
    }

    public function canManageEmployee(User $actor, User $employee): bool
    {
        if (! $this->canManage($actor)) {
            return false;
        }
        if ($actor->role === 'team_leader') {
            return $actor->id !== $employee->id && app(TeamLeaderWorkspaceService::class)->members($actor)->whereKey($employee->id)->exists();
        }

        return true;
    }
}
