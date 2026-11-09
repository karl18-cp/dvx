<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiniGameRoomPlayer extends Model
{
    protected $guarded = ['id'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(MiniGameRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
