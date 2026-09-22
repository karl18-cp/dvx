<?php

namespace App\Services;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentNotification;
use App\Models\User;

class AssessmentNotificationService
{
    public function createForUser(User $recipient, string $deduplicationKey, string $type, string $title, string $message, ?string $actionUrl = null): void
    {
        AssessmentNotification::query()->firstOrCreate(
            ['deduplication_key' => $deduplicationKey],
            ['recipient_id' => $recipient->id, 'type' => $type, 'title' => $title, 'message' => $message, 'action_url' => $actionUrl]
        );
    }

    public function create(AssessmentAssignment $assignment, string $type, string $title, string $message, ?string $actionUrl = null): void
    {
        AssessmentNotification::query()->firstOrCreate(
            ['deduplication_key' => "assignment:{$assignment->id}:{$type}"],
            ['recipient_id' => $assignment->employee_id, 'assessment_id' => $assignment->assessment_id, 'assignment_id' => $assignment->id, 'type' => $type, 'title' => $title, 'message' => $message, 'action_url' => $actionUrl ?? route('assessments.index')]
        );
    }
}
