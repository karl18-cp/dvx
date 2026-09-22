<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'title', 'description', 'instructions', 'difficulty', 'passing_score', 'time_limit_minutes', 'maximum_attempts', 'available_at', 'due_at', 'allow_retake', 'randomize_questions', 'randomize_answers', 'reduce_repeated_questions', 'show_correct_answers', 'status', 'simple_builder_enabled', 'planned_question_count', 'planned_total_points', 'created_by', 'updated_by', 'published_at', 'publish_at', 'applies_to_all_campaigns'])]
class Assessment extends Model
{
    protected function casts(): array
    {
        return [
            'passing_score' => 'decimal:2',
            'available_at' => 'datetime',
            'due_at' => 'datetime',
            'published_at' => 'datetime',
            'publish_at' => 'datetime',
            'allow_retake' => 'boolean',
            'randomize_questions' => 'boolean',
            'randomize_answers' => 'boolean',
            'reduce_repeated_questions' => 'boolean',
            'show_correct_answers' => 'boolean',
            'applies_to_all_campaigns' => 'boolean',
            'simple_builder_enabled' => 'boolean',
        ];
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trainingMaterials(): HasMany
    {
        return $this->hasMany(AssessmentTrainingMaterial::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssessmentAssignment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function randomPools(): HasMany
    {
        return $this->hasMany(AssessmentRandomPool::class)->orderBy('display_order');
    }

    public function trainingAttachments(): HasMany
    {
        return $this->hasMany(AssessmentTrainingAttachment::class)->orderBy('display_order');
    }
}
