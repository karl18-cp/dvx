<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['attachment_id', 'employee_id', 'started_at', 'last_position_seconds', 'maximum_position_seconds', 'completion_percentage', 'completed_at'])]
class AssessmentTrainingAttachmentProgress extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
