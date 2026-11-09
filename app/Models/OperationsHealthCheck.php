<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationsHealthCheck extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
