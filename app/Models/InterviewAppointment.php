<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewAppointment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'invited_at' => 'datetime',
            'last_invite_attempt_at' => 'datetime', 'calendar_sequence' => 'integer', 'reschedule_count' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InterviewAppointmentEvent::class)->latest('id');
    }
}
