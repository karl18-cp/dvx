<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'request_date', 'request_time', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_notes'])]
class OvertimeRequest extends Model
{
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    protected function casts(): array { return ['request_date' => 'date', 'reviewed_at' => 'datetime']; }
}
