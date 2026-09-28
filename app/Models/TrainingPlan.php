<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'weekdays' => 'array', 'phases' => 'array', 'first_allowance_day' => 'integer'];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function trainees()
    {
        return $this->belongsToMany(User::class, 'training_plan_enrollments')->withTimestamps();
    }
}
