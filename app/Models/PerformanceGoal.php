<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceGoal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['baseline_value' => 'decimal:2', 'target_value' => 'decimal:2', 'weight' => 'integer', 'progress' => 'integer', 'start_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
