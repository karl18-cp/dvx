<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeBankAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['account_holder', 'account_number', 'notes', 'fingerprint', 'request_key'];

    protected function casts(): array
    {
        return ['account_holder' => 'encrypted', 'account_number' => 'encrypted', 'notes' => 'encrypted', 'version' => 'integer'];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
