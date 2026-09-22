<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['scorecard_id', 'name', 'description', 'display_order'])]
class CallEvaluationScorecardCategory extends Model
{
    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(CallEvaluationScorecard::class, 'scorecard_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(CallEvaluationScorecardCriterion::class, 'category_id')->orderBy('display_order')->orderBy('id');
    }
}
