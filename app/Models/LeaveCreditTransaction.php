<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'created_by', 'type', 'amount', 'effective_date', 'cycle_start', 'cycle_end', 'reference', 'notes', 'source_key', 'source_type', 'source_id', 'automatic', 'metadata'])]
class LeaveCreditTransaction extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_date' => 'date',
            'cycle_start' => 'date',
            'cycle_end' => 'date',
            'automatic' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
