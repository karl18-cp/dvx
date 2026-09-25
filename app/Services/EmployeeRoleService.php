<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeRoleService
{
    public const ROLES = [
        'Admin' => 'admin',
        'Manager' => 'manager',
        'Team Leader' => 'team_leader',
        'Agent' => 'agent',
        'IT Admin' => 'it_admin',
        'IT Support' => 'it_support',
        'IT Developer' => 'it_developer',
    ];

    public function change(User $actor, User $employee, string $role): void
    {
        abort_unless($actor->role === 'admin', 403);
        if ($actor->is($employee) && $role !== 'admin') {
            throw ValidationException::withMessages(['position' => 'You cannot remove your own administrator role. Ask another administrator to change it.']);
        }

        DB::transaction(function () use ($actor, $employee, $role): void {
            $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
            $previous = $employee->role;
            if ($previous === $role) {
                return;
            }
            $removedMemberships = $role !== 'agent' ? $employee->teamMembership()->pluck('team_id')->all() : [];
            $removedLeaderships = $role !== 'team_leader' ? $employee->ledTeamAssignments()->pluck('team_id')->all() : [];
            if ($role !== 'agent') {
                $employee->teamMembership()->delete();
            }
            if ($role !== 'team_leader') {
                $employee->ledTeamAssignments()->delete();
            }
            $employee->update(['role' => $role, 'team' => null]);
            DB::table('assessment_activity_logs')->insert([
                'actor_id' => $actor->id, 'action' => 'Employee Role Changed', 'target_type' => User::class, 'target_id' => $employee->id,
                'metadata' => json_encode(['from_role' => $previous, 'to_role' => $role, 'removed_member_team_ids' => $removedMemberships, 'removed_leader_team_ids' => $removedLeaderships]),
                'created_at' => now(),
            ]);
        });
    }
}
