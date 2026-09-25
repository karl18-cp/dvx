<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'created_by'])]
class CampaignSchedule extends Model
{
    public function days(): HasMany
    {
        return $this->hasMany(CampaignScheduleDay::class)->orderBy('day');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
