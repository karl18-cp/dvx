<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['call_evaluation_id', 'criterion_snapshot_key', 'criterion_id', 'points_awarded', 'is_na', 'critical_failure', 'comment'])]
class CallEvaluationCriterionResult extends Model
{
    protected function casts(): array
    {
        return ['points_awarded' => 'decimal:2', 'is_na' => 'boolean', 'critical_failure' => 'boolean'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(CallEvaluation::class, 'call_evaluation_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(CallEvaluationScorecardCriterion::class, 'criterion_id');
    }
}
