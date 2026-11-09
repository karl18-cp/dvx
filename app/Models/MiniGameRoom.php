<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiniGameRoom extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['state' => 'array', 'started_at' => 'datetime', 'last_move_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(MiniGameRoomPlayer::class, 'room_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MiniGameInvitation::class, 'room_id');
    }
}
