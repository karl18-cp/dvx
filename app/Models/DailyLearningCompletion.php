<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyLearningCompletion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['learning_date' => 'date', 'is_correct' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
