<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiniGamePresence extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
