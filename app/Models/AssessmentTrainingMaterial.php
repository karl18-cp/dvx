<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'title', 'description', 'type', 'content', 'storage_disk', 'storage_key', 'original_filename', 'stored_filename', 'mime_type', 'file_size', 'duration_seconds', 'is_required', 'required_completion_percentage', 'display_order', 'is_active', 'created_by'])]
class AssessmentTrainingMaterial extends Model
{
    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function trainingProgress(): HasMany
    {
        return $this->hasMany(AssessmentTrainingProgress::class, 'material_id');
    }
}
