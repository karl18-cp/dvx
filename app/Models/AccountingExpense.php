<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingExpense extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'expense_date' => 'date:Y-m-d', 'voided_at' => 'datetime'];
    }
}
