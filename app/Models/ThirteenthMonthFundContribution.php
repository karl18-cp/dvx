<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThirteenthMonthFundContribution extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contribution_month' => 'date:Y-m-d',
            'amount_cents' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
