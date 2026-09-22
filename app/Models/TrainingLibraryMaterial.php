<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'skill_id', 'title', 'description', 'type', 'content', 'storage_disk', 'storage_key', 'original_filename', 'stored_filename', 'mime_type', 'file_size', 'duration_seconds', 'status', 'created_by', 'applies_to_all_campaigns'])]
class TrainingLibraryMaterial extends Model
{
    protected function casts(): array
    {
        return ['applies_to_all_campaigns' => 'boolean'];
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'training_library_material_campaign');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class, 'skill_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssessmentTrainingAttachment::class, 'library_material_id');
    }
}
