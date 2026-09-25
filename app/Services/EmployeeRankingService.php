<?php

namespace App\Services;

use App\Models\EmployeeFormResponse;
use App\Models\User;
use Illuminate\Support\Collection;

class EmployeeRankingService
{
    public function leaders(?int $limit = null, ?User $viewer = null): Collection
    {
        $query = EmployeeFormResponse::query()->whereNotNull('employee_id')->where('points', '>', 0)
            ->select('employee_id')->selectRaw('SUM(points) as points, COUNT(*) as submissions')
            ->groupBy('employee_id')->orderByDesc('points')->orderBy('employee_id');
        if ($viewer?->role === 'team_leader') {
            $query->whereIn('employee_id', app(TeamLeaderWorkspaceService::class)->members($viewer)->select('users.id'));
        }
        if ($limit !== null) {
            $query->limit($limit);
        }
        $leaders = $query->get();
        $users = User::whereIn('id', $leaders->pluck('employee_id'))->get(['id', 'name', 'username', 'profile_photo_path'])->keyBy('id');

        return $leaders->map(fn ($row) => ['id' => $row->employee_id, 'name' => $users->get($row->employee_id)?->name ?? 'Former employee', 'username' => $users->get($row->employee_id)?->username, 'avatar' => $users->get($row->employee_id)?->avatar ?? '', 'points' => (int) $row->points, 'submissions' => (int) $row->submissions]);
    }
}
