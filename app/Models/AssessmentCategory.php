<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'is_active'])]
class AssessmentCategory extends Model
{
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'category_id');
    }
}
