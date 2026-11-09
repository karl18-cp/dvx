<?php

use App\Models\BackupRun;
use App\Models\WorkplaceEmailDelivery;
use App\Services\AssessmentScheduleService;
use App\Services\BackupService;
use App\Services\CampaignIntegrityService;
use App\Services\DocumentExpirationService;
use App\Services\OperationsHealthService;
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

Artisan::command('operations:heartbeat', function (OperationsHealthService $service) {
    $heartbeat = $service->heartbeat();
    $this->info('Operations heartbeat: '.$heartbeat->checked_at?->toISOString());
})->purpose('Record proof that the production scheduler is executing');

Artisan::command('operations:health', function (OperationsHealthService $service) {
    $snapshot = $service->snapshot();
    $this->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return $snapshot['summary']['critical'] > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Print the current operations health snapshot');

Schedule::command('operations:heartbeat')->everyMinute()->withoutOverlapping();

Artisan::command('documents:scan-expirations', function (DocumentExpirationService $service) {
    $this->info(json_encode($service->scan(), JSON_THROW_ON_ERROR));
})->purpose('Create administrator alerts for expiring contracts and compliance documents');

Schedule::command('documents:scan-expirations')->dailyAt('08:00')->timezone('Asia/Manila')->withoutOverlapping();

Artisan::command('system:backup', function (BackupService $service) {
    $run = $service->run();
    $this->{$run->status === 'completed' ? 'info' : 'error'}("Backup #{$run->id}: {$run->status}");

    return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
})->purpose('Create an encrypted database and private-file backup');

Artisan::command('system:backup-test {id?}', function (BackupService $service) {
    $query = BackupRun::query()->where('status', 'completed');
    $run = $this->argument('id') ? $query->findOrFail($this->argument('id')) : $query->latest('id')->firstOrFail();
    $result = $service->testRestore($run);
    $this->info("Backup #{$run->id} restore test passed: ".json_encode($result, JSON_THROW_ON_ERROR));
})->purpose('Decrypt and verify a backup in an isolated restore test');

Schedule::command('system:backup')
    ->dailyAt(config('backups.schedule'))
    ->timezone(config('backups.timezone'))
    ->withoutOverlapping(360);

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
