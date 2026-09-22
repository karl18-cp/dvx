<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['coaching_record_id', 'library_material_id', 'is_required', 'assigned_by'])]
class CoachingTrainingAssignment extends Model
{
    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function coaching(): BelongsTo
    {
        return $this->belongsTo(CoachingRecord::class, 'coaching_record_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(TrainingLibraryMaterial::class, 'library_material_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(CoachingTrainingProgress::class);
    }
}
