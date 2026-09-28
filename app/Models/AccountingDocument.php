<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['attachment_path', 'invoice_key', 'request_key'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_cents' => 'integer', 'issue_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'voided_at' => 'datetime'];
    }
}
