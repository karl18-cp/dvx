<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['skill_id', 'category_id', 'question_text', 'question_type', 'difficulty', 'points', 'feedback', 'status', 'created_by', 'applies_to_all_campaigns'])]
class AssessmentBankQuestion extends Model
{
    protected function casts(): array
    {
        return ['points' => 'decimal:2', 'applies_to_all_campaigns' => 'boolean'];
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'assessment_bank_question_campaign');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssessmentBankQuestionOption::class, 'bank_question_id')->orderBy('display_order');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class, 'skill_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }
}
