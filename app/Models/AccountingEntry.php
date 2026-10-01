<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $source_snapshot
 * @property-read User $employee
 */
class AccountingEntry extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'deduction_cents' => 0, 'currency' => 'PHP'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'gross_cents' => 'integer', 'deduction_cents' => 'integer', 'net_cents' => 'integer', 'source_snapshot' => 'array', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
