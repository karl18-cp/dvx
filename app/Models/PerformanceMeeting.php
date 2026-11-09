<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PerformanceMeeting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'held_at' => 'datetime', 'action_items' => 'array', 'next_meeting_date' => 'date:Y-m-d'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function acknowledgement(): HasOne
    {
        return $this->hasOne(PerformanceAcknowledgement::class, 'record_id')->where('record_type', 'meeting');
    }
}
