<?php

use App\Services\AssessmentScheduleService;
use App\Services\CampaignIntegrityService;
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
