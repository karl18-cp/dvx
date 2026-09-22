<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'question_id', 'question_snapshot', 'answer', 'points_awarded', 'is_correct', 'requires_manual_review', 'grader_feedback', 'graded_by', 'graded_at'])]
class AssessmentAnswer extends Model
{
    protected function casts(): array
    {
        return ['question_snapshot' => 'array', 'answer' => 'array', 'is_correct' => 'boolean', 'requires_manual_review' => 'boolean', 'graded_at' => 'datetime', 'points_awarded' => 'decimal:2'];
    }

    /** @return BelongsTo<AssessmentAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class);
    }

    /** @return BelongsTo<AssessmentQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
