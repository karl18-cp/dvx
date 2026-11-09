<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicUpdateComment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }

    public function publicUpdate(): BelongsTo
    {
        return $this->belongsTo(PublicUpdate::class, 'public_update_id');
    }
}
