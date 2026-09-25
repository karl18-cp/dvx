<?php

namespace App\Services;

use App\Mail\ApplicantSubmission;
use App\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecruitmentEmailService
{
    public function sendStatusUpdate(JobApplication $application): ?bool
    {
        return DB::transaction(function () use ($application) {
            $application = JobApplication::query()->lockForUpdate()->findOrFail($application->id);
            if (! $application->applicant_email_pending) {
                return null;
            }
            if (! $this->configured()) {
                return false;
            }
            $label = match ($application->applicant_stage) {
                'for_screening' => 'For Screening',
                'for_final_interview' => 'For Final Interview',
                'passed' => 'Passed',
                'failed' => 'Failed',
            };
            try {
                Mail::to($application->email)->send(new \App\Mail\ApplicantStatusUpdated(
                    $application->first_name.' '.$application->last_name,
                    $application->position, $label, $application->applicant_update,
                    rtrim(config('app.url'), '/').'/applicant-portal',
                    $application->scheduleLabel(),
                    $application->scheduled_start_at ? $application->scheduled_start_at->setTimezone('Asia/Manila')->format('F j, Y, g:i A').' (Asia/Manila)' : null,
                ));
            } catch (\Throwable $exception) {
                Log::warning('Applicant status email failed', ['application_id' => $application->id, 'exception_type' => $exception::class]);

                return false;
            }
            $application->forceFill(['applicant_email_pending' => false, 'applicant_emailed_at' => now()])->save();

            return true;
        });
    }

    public function configured(): bool
    {
        return config('mail.default') === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'));
    }

    public function send(JobApplication $application): bool
    {
        // Serialize retries so double-clicking cannot send two copies.
        return DB::transaction(function () use ($application) {
            $application = JobApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($application->recruitment_emailed_at) {
                return true;
            }
            if (! $this->configured()) {
                $application->forceFill(['recruitment_email_status' => 'not_configured'])->save();

                return false;
            }
            try {
                Mail::to(config('recruitment.inbox'))->send(new ApplicantSubmission($application));
            } catch (\Throwable $exception) {
                // Never discard an application or disclose SMTP details to an applicant.
                $application->forceFill(['recruitment_email_status' => 'failed'])->save();
                Log::warning('Recruitment email failed', ['application_id' => $application->id, 'exception_type' => $exception::class]);

                return false;
            }
            $application->forceFill(['recruitment_email_status' => 'sent', 'recruitment_emailed_at' => now()])->save();

            return true;
        });
    }
}
