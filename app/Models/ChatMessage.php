<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['request_id', 'conversation_id', 'sender_id', 'sender_name', 'body'])]
class ChatMessage extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('DiverText messages cannot be edited.');
        });
        static::deleting(function (): never {
            throw new \LogicException('DiverText messages cannot be unsent.');
        });
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
