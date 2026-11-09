<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceAcknowledgement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
