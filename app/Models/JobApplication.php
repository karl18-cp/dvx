<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'phone_normalized', 'position', 'location', 'years_experience', 'message', 'resume_disk', 'resume_path', 'resume_original_name', 'resume_mime', 'resume_size', 'screening_status', 'interview_status', 'application_status', 'applicant_stage', 'internal_notes', 'applicant_update', 'reviewed_by', 'reviewed_at'])]
class JobApplication extends Model
{
    protected function casts(): array
    {
        return ['years_experience' => 'integer', 'resume_size' => 'integer', 'reviewed_at' => 'datetime', 'scheduled_start_at' => 'datetime'];
    }

    public function scheduleLabel(): ?string
    {
        return match ($this->applicant_stage) {
            'for_screening' => 'Screening interview',
            'for_final_interview' => 'Final interview',
            'passed' => 'Training',
            default => null,
        };
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
