<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'skill_id', 'source_bank_question_id', 'generated_for_attempt_id', 'question_text', 'question_type', 'points', 'correct_answer', 'feedback', 'display_order', 'is_required'])]
class AssessmentQuestion extends Model
{
    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'points' => 'decimal:2'];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class, 'skill_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssessmentQuestionOption::class, 'question_id')->orderBy('display_order');
    }
}
