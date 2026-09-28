<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkplaceEmailDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
