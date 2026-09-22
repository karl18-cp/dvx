<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'description', 'applies_to_all_campaigns', 'status', 'passing_score', 'created_by', 'updated_by', 'archived_at'])]
class CallEvaluationScorecard extends Model
{
    protected function casts(): array
    {
        return ['applies_to_all_campaigns' => 'boolean', 'passing_score' => 'decimal:2', 'archived_at' => 'datetime'];
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'call_evaluation_scorecard_campaign');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(CallEvaluationScorecardCategory::class, 'scorecard_id')->orderBy('display_order')->orderBy('id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(CallEvaluation::class, 'scorecard_id');
    }

    public function criteria(): HasManyThrough
    {
        return $this->hasManyThrough(CallEvaluationScorecardCriterion::class, CallEvaluationScorecardCategory::class, 'scorecard_id', 'category_id');
    }
}
