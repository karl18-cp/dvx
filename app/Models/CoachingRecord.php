<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_id', 'campaign_id', 'campaign_name', 'team_id', 'team_name', 'coach_id', 'skill_id', 'assessment_id', 'call_evaluation_id', 'coaching_date', 'type', 'status', 'summary', 'strengths', 'areas_for_improvement', 'action_plan', 'follow_up_date', 'acknowledged_at', 'completed_at', 'completed_by'])]
class CoachingRecord extends Model
{
    protected function casts(): array
    {
        return ['coaching_date' => 'date', 'follow_up_date' => 'date', 'acknowledged_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function callEvaluation(): BelongsTo
    {
        return $this->belongsTo(CallEvaluation::class);
    }

    public function trainingAssignments(): HasMany
    {
        return $this->hasMany(CoachingTrainingAssignment::class);
    }
}
