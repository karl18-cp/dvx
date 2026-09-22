<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['recipient_id', 'assessment_id', 'assignment_id', 'type', 'title', 'message', 'action_url', 'deduplication_key', 'read_at'])]
class AssessmentNotification extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
