<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['day', 'no_schedule', 'time_in', 'time_out', 'break_start', 'break_end'])]
class CampaignScheduleDay extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['day' => 'integer', 'no_schedule' => 'boolean'];
    }
}
