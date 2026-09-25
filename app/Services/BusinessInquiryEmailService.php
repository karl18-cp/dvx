<?php

namespace App\Services;

use App\Mail\BusinessInquiryReceived;
use App\Models\BusinessInquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BusinessInquiryEmailService
{
    public function send(BusinessInquiry $inquiry): bool
    {
        return DB::transaction(function () use ($inquiry) {
            $inquiry = BusinessInquiry::query()->lockForUpdate()->findOrFail($inquiry->id);
            if ($inquiry->emailed_at) return true;
            if (! app(RecruitmentEmailService::class)->configured()) {
                $inquiry->update(['email_status' => 'not_configured']);
                return false;
            }
            try {
                Mail::to(config('recruitment.inbox'))->send(new BusinessInquiryReceived($inquiry));
            } catch (\Throwable $exception) {
                $inquiry->update(['email_status' => 'failed']);
                Log::warning('Business inquiry email failed', ['inquiry_id' => $inquiry->id, 'exception_type' => $exception::class]);
                return false;
            }
            $inquiry->update(['email_status' => 'sent', 'emailed_at' => now()]);
            return true;
        });
    }
}
