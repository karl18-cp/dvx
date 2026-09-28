<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantExamSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'question_count' => 'integer', 'duration_minutes' => 'integer', 'passing_percent' => 'integer'];
    }
}
