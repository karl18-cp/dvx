<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property array<int, float> $encrypted_descriptor
 * @property string $model_version
 * @property Carbon $consented_at
 * @property Carbon $enrolled_at
 * @property Carbon|null $last_verified_at
 */
#[Fillable([
    'user_id',
    'encrypted_descriptor',
    'model_version',
    'consented_at',
    'enrolled_at',
    'last_verified_at',
])]
class FaceCredential extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encrypted_descriptor' => 'encrypted:array',
            'consented_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }
}
