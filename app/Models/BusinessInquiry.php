<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessInquiry extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['meeting_at' => 'immutable_datetime', 'emailed_at' => 'immutable_datetime', 'custom_answers' => 'array']; }
}
