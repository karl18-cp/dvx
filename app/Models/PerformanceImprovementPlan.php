<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PerformanceImprovementPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['success_criteria' => 'array', 'progress_updates' => 'array', 'start_date' => 'date:Y-m-d', 'review_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'closed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function sourceReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'source_review_id');
    }

    public function acknowledgement(): HasOne
    {
        return $this->hasOne(PerformanceAcknowledgement::class, 'record_id')->where('record_type', 'pip');
    }
}
