<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    private const SENSITIVE = ['password', 'token', 'secret', 'descriptor', 'account_number', 'fingerprint', 'recovery_codes', 'remember_token'];

    public function record(?User $actor, string $category, string $action, Model|string $target, ?int $targetId = null, array $metadata = [], ?int $subjectId = null): ActivityLog
    {
        $type = $target instanceof Model ? $target::class : $target;
        $id = $target instanceof Model ? (int) $target->getKey() : (int) $targetId;
        $request = request();

        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'subject_id' => $subjectId,
            'category' => $category,
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
            'metadata' => $this->sanitize($metadata),
            'ip_address' => app()->runningInConsole() ? null : $request->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    public function sanitize(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            $normalized = mb_strtolower((string) $key);
            if (collect(self::SENSITIVE)->contains(fn ($needle) => str_contains($normalized, $needle))) {
                $metadata[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->sanitize($value);
            }
        }

        return $metadata;
    }
}
