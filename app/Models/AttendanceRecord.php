<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'attendance_date',
    'status',
    'time_in',
    'lunch_out',
    'lunch_in',
    'time_out',
    'notes',
])]
class AttendanceRecord extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approval_snapshot' => 'array',
            'worked_minutes' => 'integer',
            'leave_minutes' => 'integer',
            'total_minutes' => 'integer',
            'schedule_snapshot' => 'array',
            'actual_time_in' => 'datetime',
            'actual_lunch_out' => 'datetime',
            'actual_lunch_in' => 'datetime',
            'actual_time_out' => 'datetime',
            'attendance_date' => 'date',
            'time_in' => 'datetime',
            'lunch_out' => 'datetime',
            'lunch_in' => 'datetime',
            'time_out' => 'datetime',
        ];
    }
}
