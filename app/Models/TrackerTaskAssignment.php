<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'assignee_name', 'assignee_role', 'completed_items'])]
class TrackerTaskAssignment extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(TrackerTask::class, 'tracker_task_id');
    }

    protected function casts(): array
    {
        return ['completed_items' => 'array', 'version' => 'integer', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
