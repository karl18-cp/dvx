<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'skill_id', 'label', 'guidance', 'points_possible', 'is_required', 'is_critical', 'allows_na', 'display_order'])]
class CallEvaluationScorecardCriterion extends Model
{
    protected function casts(): array
    {
        return ['points_possible' => 'decimal:2', 'is_required' => 'boolean', 'is_critical' => 'boolean', 'allows_na' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CallEvaluationScorecardCategory::class, 'category_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class, 'skill_id');
    }
}
