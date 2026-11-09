<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountStatusService
{
    public function visible(User $actor)
    {
        abort_unless(app(RolePermissionService::class)->can($actor, 'account_status.manage'), 403);

        return match ($actor->role) {
            'team_leader' => app(TeamLeaderWorkspaceService::class)->members($actor),
            'qa_admin' => User::whereIn('role', ['agent', 'team_leader', 'trainee']),
            default => User::query(),
        };
    }

    public function update(User $actor, User $employee, string $status, ?string $notes = null): void
    {
        DB::transaction(function () use ($actor, $employee, $status, $notes): void {
            $employee = User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->visible($actor)->whereKey($employee->id)->exists(), 403);
            if ($employee->is($actor)) {
                throw ValidationException::withMessages(['status' => 'Ask another administrator to change your account status.']);
            }
            $trainee = $employee->role === 'trainee';
            $allowed = $trainee ? ['in_training', 'graduated', 'rejected'] : ['active', 'floating', 'resigned', 'suspended', 'terminated'];
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Choose a valid status for this account.']);
            }
            $before = ['status' => $employee->status, 'training_status' => $employee->training_status];
            if (($trainee ? $employee->training_status : $employee->status) === $status && (! $trainee || $employee->status === ($status === 'rejected' ? 'terminated' : 'active'))) {
                return;
            }
            if ($trainee) {
                $employee->forceFill(['training_status' => $status, 'status' => $status === 'rejected' ? 'terminated' : 'active', 'training_reviewed_at' => $status === 'in_training' ? null : now(), 'training_reviewed_by' => $actor->id, 'training_review_notes' => $notes]);
            } else {
                $employee->status = $status;
            }
            if (! $employee->canAccessAccount()) {
                $employee->remember_token = null;
                DB::table('sessions')->where('user_id', $employee->id)->delete();
            }
            $employee->save();
            app(AuditLogService::class)->record($actor, 'account_status', 'Account status changed', $employee, metadata: ['before' => $before, 'after' => ['status' => $employee->status, 'training_status' => $employee->training_status], 'notes' => $notes], subjectId: $employee->id);
        });
    }
}
