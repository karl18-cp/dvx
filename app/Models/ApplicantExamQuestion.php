<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantExamQuestion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct_index' => 'integer', 'approved' => 'boolean'];
    }
}
