<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'employee_id', 'campaign_id', 'campaign_name', 'assignment_id', 'attempt_number', 'question_snapshot', 'flagged_question_ids', 'started_at', 'expires_at', 'submitted_at', 'status', 'points_earned', 'total_points', 'percentage', 'passed', 'time_taken_seconds'])]
class AssessmentAttempt extends Model
{
    protected function casts(): array
    {
        return ['question_snapshot' => 'array', 'flagged_question_ids' => 'array', 'started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime', 'passed' => 'boolean', 'points_earned' => 'decimal:2', 'total_points' => 'decimal:2', 'percentage' => 'decimal:2'];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<AssessmentAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssessmentAssignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return HasMany<AssessmentAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'attempt_id');
    }

    /** @return HasMany<AssessmentSkillResult, $this> */
    public function skillResults(): HasMany
    {
        return $this->hasMany(AssessmentSkillResult::class, 'attempt_id');
    }
}
