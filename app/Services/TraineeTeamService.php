<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TraineeTeamService
{
    public function validateTeam(User $member, Team $team, string $field = 'team_id'): void
    {
        if ($member->role !== 'trainee') {
            return;
        }
        if (! $member->canAccessAccount()) {
            throw ValidationException::withMessages([$field => 'Only active or passed trainee accounts can be assigned.']);
        }
        if ((int) $member->training_campaign_id !== (int) $team->campaign_id) {
            throw ValidationException::withMessages([$field => 'Choose a team in the trainee’s assigned training campaign.']);
        }
        if (! $team->leaderAssignment()->whereHas('user', fn ($q) => $q->where('role', 'team_leader')->where('status', 'active'))->exists()) {
            throw ValidationException::withMessages([$field => 'Assign an active team leader to this team first.']);
        }
    }

    public function assign(User $actor, User $trainee, Team $team): void
    {
        abort_unless($actor->role === 'admin' && $actor->canAccessAccount(), 403);
        DB::transaction(function () use ($actor, $trainee, $team) {
            $trainee = User::whereKey($trainee->id)->lockForUpdate()->firstOrFail();
            abort_unless($trainee->role === 'trainee', 422);
            $this->validateTeam($trainee, $team);
            $membership = TeamMember::where('user_id', $trainee->id)->lockForUpdate()->first();
            $before = $membership?->team_id;
            TeamMember::updateOrCreate(['user_id' => $trainee->id], ['team_id' => $team->id]);
            DB::table('assessment_activity_logs')->insert(['actor_id' => $actor->id, 'action' => 'Trainee team assigned', 'target_type' => User::class, 'target_id' => $trainee->id, 'metadata' => json_encode(['from_team_id' => $before, 'to_team_id' => $team->id]), 'created_at' => now()]);
        });
    }
}
