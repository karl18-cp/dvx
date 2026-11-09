<?php

namespace App\Services;

use App\Models\AssessmentNotification;
use App\Models\ComplianceDocument;
use App\Models\EmployeeContract;
use App\Models\User;
use Carbon\CarbonImmutable;

class DocumentExpirationService
{
    public function scan(): array
    {
        $today = CarbonImmutable::today('Asia/Manila');
        $recipients = User::query()->where('status', 'active')->get()
            ->filter(fn (User $user) => $user->hasPermission('compliance_documents.manage'));
        $created = 0;

        ComplianceDocument::query()->with(['employee:id,name,username', 'applicant:id,first_name,last_name'])
            ->where('status', 'active')->whereDate('expires_at', '<=', $today->addDays(365))
            ->chunkById(100, function ($documents) use ($today, $recipients, &$created) {
                foreach ($documents as $document) {
                    $days = (int) $today->diffInDays($document->expires_at, false);
                    if ($days > $document->remind_days_before) {
                        continue;
                    }
                    $subject = $document->employee?->name
                        ?? trim(($document->applicant?->first_name ?? '').' '.($document->applicant?->last_name ?? ''))
                        ?: 'Unassigned record';
                    $created += $this->notify(
                        $recipients,
                        "compliance-document:{$document->id}:{$document->expires_at->toDateString()}",
                        $this->title($days),
                        "{$document->title} for {$subject} {$this->timing($days)}.",
                    );
                }
            });

        EmployeeContract::query()->with('employee:id,name,username')
            ->whereNull('archived_at')->whereNotNull('end_date')
            ->where(function ($query) {
                $query->where('status', 'active')->orWhereIn('signing_status', ['issued', 'signed']);
            })->where('signing_status', '!=', 'superseded')->orderByDesc('version')->get()
            ->unique('version_group')->each(function (EmployeeContract $contract) use ($today, $recipients, &$created) {
                $days = (int) $today->diffInDays($contract->end_date, false);
                if ($days > 30) {
                    return;
                }
                $created += $this->notify(
                    $recipients,
                    "contract-expiration:{$contract->id}:{$contract->end_date->toDateString()}",
                    $this->title($days),
                    "{$contract->title} for {$contract->employee?->name} {$this->timing($days)}.",
                );
            });

        return ['notifications_created' => $created, 'recipients' => $recipients->count()];
    }

    private function notify($recipients, string $key, string $title, string $message): int
    {
        $created = 0;
        foreach ($recipients as $recipient) {
            $before = AssessmentNotification::query()->where('deduplication_key', "{$key}:{$recipient->id}")->exists();
            app(AssessmentNotificationService::class)->createForUser(
                $recipient,
                "{$key}:{$recipient->id}",
                'document_expiration',
                $title,
                $message,
                '/document-expirations',
            );
            $created += $before ? 0 : 1;
        }

        return $created;
    }

    private function title(int $days): string
    {
        return $days < 0 ? 'Document has expired' : ($days === 0 ? 'Document expires today' : 'Document expiring soon');
    }

    private function timing(int $days): string
    {
        return $days < 0 ? 'expired '.abs($days).' day(s) ago' : ($days === 0 ? 'expires today' : "expires in {$days} day(s)");
    }
}
