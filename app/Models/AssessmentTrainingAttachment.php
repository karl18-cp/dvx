<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'library_material_id', 'is_required', 'required_completion_percentage', 'display_order'])]
class AssessmentTrainingAttachment extends Model
{
    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(TrainingLibraryMaterial::class, 'library_material_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(AssessmentTrainingAttachmentProgress::class, 'attachment_id');
    }
}
