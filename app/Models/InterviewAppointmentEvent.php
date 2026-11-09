<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewAppointmentEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'previous_starts_at' => 'datetime', 'previous_ends_at' => 'datetime',
            'new_starts_at' => 'datetime', 'new_ends_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(InterviewAppointment::class, 'interview_appointment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
