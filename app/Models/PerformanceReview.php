<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PerformanceReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_month' => 'date:Y-m-d', 'overall_rating' => 'decimal:2', 'kpis' => 'array', 'shared_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function acknowledgement(): HasOne
    {
        return $this->hasOne(PerformanceAcknowledgement::class, 'record_id')->where('record_type', 'review');
    }
}
