<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingEntry extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'deduction_cents' => 0, 'currency' => 'PHP'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'gross_cents' => 'integer', 'deduction_cents' => 'integer', 'net_cents' => 'integer', 'source_snapshot' => 'array', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
