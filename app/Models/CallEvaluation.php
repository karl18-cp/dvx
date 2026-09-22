<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['employee_id', 'evaluator_id', 'scorecard_id', 'campaign_id', 'campaign_name', 'team_id', 'team_name', 'scorecard_name', 'scorecard_snapshot', 'call_at', 'call_direction', 'call_reference', 'status', 'storage_disk', 'storage_key', 'original_filename', 'mime_type', 'file_size', 'duration_seconds', 'uploaded_by', 'uploaded_at', 'document_storage_disk', 'document_storage_key', 'document_original_filename', 'document_mime_type', 'document_file_size', 'document_uploaded_by', 'document_uploaded_at', 'strengths', 'areas_for_improvement', 'overall_feedback', 'recommended_action', 'points_earned', 'points_possible', 'percentage', 'result', 'has_critical_failure', 'finalized_at', 'finalized_by'])]
class CallEvaluation extends Model
{
    protected function casts(): array
    {
        return ['scorecard_snapshot' => 'array', 'call_at' => 'datetime', 'uploaded_at' => 'datetime', 'document_uploaded_at' => 'datetime', 'finalized_at' => 'datetime', 'has_critical_failure' => 'boolean', 'points_earned' => 'decimal:2', 'points_possible' => 'decimal:2', 'percentage' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(CallEvaluationScorecard::class, 'scorecard_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function criterionResults(): HasMany
    {
        return $this->hasMany(CallEvaluationCriterionResult::class);
    }

    public function coachingRecord(): HasOne
    {
        return $this->hasOne(CoachingRecord::class);
    }
}
