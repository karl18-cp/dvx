<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'start_date', 'end_date', 'number_of_days', 'leave_type', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_notes'])]
class LeaveRequest extends Model
{
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date', 'reviewed_at' => 'datetime']; }
}
