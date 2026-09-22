<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'abbreviation', 'description', 'is_active'])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class);
    }

    public function trainingMaterials(): BelongsToMany
    {
        return $this->belongsToMany(TrainingLibraryMaterial::class, 'training_library_material_campaign');
    }

    public function bankQuestions(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentBankQuestion::class);
    }

    public function callEvaluationScorecards(): BelongsToMany
    {
        return $this->belongsToMany(CallEvaluationScorecard::class, 'call_evaluation_scorecard_campaign');
    }
}
