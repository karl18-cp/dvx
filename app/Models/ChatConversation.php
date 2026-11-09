<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['conversation_key', 'type', 'name', 'created_by', 'first_user_id', 'second_user_id', 'team_id'])]
class ChatConversation extends Model
{
    public function firstUser()
    {
        return $this->belongsTo(User::class, 'first_user_id');
    }

    public function secondUser()
    {
        return $this->belongsTo(User::class, 'second_user_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_conversation_members', 'conversation_id', 'user_id')->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }
}
