<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicUpdate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PublicUpdateComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', 'approved');
    }
}
