<?php

use App\Models\WorkplaceEmailDelivery;
use App\Services\AssessmentScheduleService;
use App\Services\CampaignIntegrityService;
use App\Services\WorkplaceEmailService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('assessments:process-schedule', function (AssessmentScheduleService $service) {
    $this->info(json_encode($service->process(), JSON_THROW_ON_ERROR));
})->purpose('Publish scheduled assessments and create assessment reminders');

Schedule::command('assessments:process-schedule')->everyTenMinutes()->withoutOverlapping();

Artisan::command('workplace:email-notifications', function (WorkplaceEmailService $service) {
    $this->info('New email deliveries: '.$service->collect());
})->purpose('Collect relevant employee alerts and queue email delivery');

Artisan::command('workplace:email-chat', function (WorkplaceEmailService $service) {
    $this->info('New message digests: '.$service->collectChat());
})->purpose('Queue unread DiverText summaries without exposing message content');

Artisan::command('workplace:email-rankings', function (WorkplaceEmailService $service) {
    $this->info('New weekly summaries: '.$service->collectWeekly());
})->purpose('Queue last week ranking progress with recipient-scoped results');

Artisan::command('workplace:email-retry', function (WorkplaceEmailService $service) {
    $count = WorkplaceEmailDelivery::where('status', 'failed')->update(['status' => 'pending', 'attempts' => 0, 'queued_at' => null]);
    if ($service->start()) {
        $service->dispatchPending();
    }
    $this->info('Failed deliveries reset for retry: '.$count);
})->purpose('Retry failed employee email deliveries without resending successful mail');

Schedule::command('workplace:email-notifications')->everyMinute()->withoutOverlapping();
Schedule::command('workplace:email-chat')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('workplace:email-rankings')->weeklyOn(1, '09:00')->timezone('Asia/Manila')->withoutOverlapping();

Artisan::command('campaigns:integrity', function (CampaignIntegrityService $service) {
    $result = $service->inspect();
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $blocking = collect($result)->filter(fn ($value, $key) => is_int($value) && $value > 0 && (
        str_starts_with($key, 'campaign_era_invalid_')
        || str_starts_with($key, 'orphan_')
        || str_starts_with($key, 'duplicate_')
        || $key === 'campaign_era_assignment_attempt_mismatches'
    ));

    return $blocking->isEmpty() ? self::SUCCESS : self::FAILURE;
})->purpose('Audit Campaign references, immutable snapshots, legacy unknowns, and assignment/attempt consistency');
