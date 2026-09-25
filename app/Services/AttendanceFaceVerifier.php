<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class AttendanceFaceVerifier
{
    public function verify(User $user, array $frames, string $direction): void
    {
        $credential = $user->faceCredential;
        if (! $credential || $credential->model_version !== config('face.model_version')) {
            throw ValidationException::withMessages(['face' => 'Your account needs a compatible face enrollment from an administrator.']);
        }
        $process = new Process([config('face.node_binary'), base_path('scripts/verify-attendance-face.cjs')], base_path(), null, json_encode([
            'frames' => $frames, 'descriptor' => $credential->encrypted_descriptor, 'direction' => $direction,
            'minimumSimilarity' => config('face.minimum_similarity'), 'minimumLiveness' => config('face.minimum_liveness'),
        ], JSON_THROW_ON_ERROR), 60);
        try {
            $process->run();
            $result = json_decode(trim($process->getOutput()), true);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['face' => 'Face verification is unavailable. Attendance was not recorded. Please retry or contact an administrator.']);
        }
        if (! $process->isSuccessful() || ! is_array($result) || ($result['accepted'] ?? false) !== true) {
            $message = ($result['reason'] ?? '') === 'verification_failed'
                ? 'Face verification did not pass. Use your own enrolled face, follow the movement prompts, and try again in even lighting.'
                : 'Face verification could not finish. Attendance was not recorded. Please retry or contact an administrator.';
            throw ValidationException::withMessages(['face' => $message]);
        }
    }
}
