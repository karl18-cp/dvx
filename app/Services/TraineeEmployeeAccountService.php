<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TraineeEmployeeAccountService
{
    public function create(User $actor, User $trainee): User
    {
        abort_unless($actor->role === 'admin' && $actor->status === 'active', 403);
        $photo = null;
        try {
            return DB::transaction(function () use ($actor, $trainee, &$photo): User {
                $trainee = User::whereKey($trainee->id)->lockForUpdate()->firstOrFail();
                if ($trainee->role !== 'trainee' || $trainee->training_status !== 'graduated') {
                    throw ValidationException::withMessages(['employee' => 'Only a passed trainee can receive a separate employee account.']);
                }
                if ($trainee->employeeAccount()->exists()) {
                    throw ValidationException::withMessages(['employee' => 'An employee account already exists for this trainee.']);
                }
                $username = app(EmployeeNumberService::class)->next(allocate: true);
                $employee = User::create(['username' => $username, 'name' => $trainee->name, 'email' => $trainee->email, 'password' => $username, 'role' => 'agent', 'status' => 'active']);
                $employee->forceFill(['source_trainee_id' => $trainee->id, 'campaign_schedule_id' => $trainee->campaign_schedule_id])->save();
                if ($personal = $trainee->personalInformation) {
                    $copy = $personal->replicate();
                    $copy->user_id = $employee->id;
                    $copy->save();
                }
                if ($credential = $trainee->faceCredential) {
                    $copy = $credential->replicate();
                    $copy->user_id = $employee->id;
                    $copy->last_verified_at = null;
                    $copy->save();
                }
                if ($trainee->profile_photo_path && Storage::disk('local')->exists($trainee->profile_photo_path)) {
                    $photo = 'profile-photos/'.Str::uuid().'.'.pathinfo($trainee->profile_photo_path, PATHINFO_EXTENSION);
                    if (! Storage::disk('local')->copy($trainee->profile_photo_path, $photo)) {
                        throw new \RuntimeException('Could not copy the profile photo.');
                    }
                    $employee->forceFill(['profile_photo_path' => $photo])->save();
                }
                DB::table('assessment_activity_logs')->insert(['actor_id' => $actor->id, 'action' => 'Employee account created from trainee', 'target_type' => User::class, 'target_id' => $employee->id, 'metadata' => json_encode(['trainee_id' => $trainee->id, 'trainee_username' => $trainee->username, 'employee_username' => $username], JSON_THROW_ON_ERROR), 'created_at' => now()]);

                return $employee;
            });
        } catch (\Throwable $error) {
            if ($photo) {
                Storage::disk('local')->delete($photo);
            }
            throw $error;
        }
    }
}
