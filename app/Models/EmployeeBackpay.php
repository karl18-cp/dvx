<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBackpay extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_key', 'user_id', 'separation_date', 'separation_status', 'calendar_year',
        'eligible_basic_cents', 'thirteenth_month_cents', 'last_payroll_cents',
        'additions_cents', 'deductions_cents', 'total_cents', 'status', 'claimed_at',
        'notes', 'source_snapshot', 'created_by', 'updated_by',
    ];

    protected $hidden = ['request_key', 'source_snapshot'];

    protected function casts(): array
    {
        return [
            'separation_date' => 'date:Y-m-d',
            'claimed_at' => 'date:Y-m-d',
            'source_snapshot' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
