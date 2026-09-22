<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['coaching_training_assignment_id', 'employee_id', 'started_at', 'last_position_seconds', 'maximum_position_seconds', 'completion_percentage', 'completed_at'])]
class CoachingTrainingProgress extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'completion_percentage' => 'decimal:2'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CoachingTrainingAssignment::class, 'coaching_training_assignment_id');
    }
}
