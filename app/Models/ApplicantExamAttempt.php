<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantExamAttempt extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'answers' => 'array', 'passed' => 'boolean', 'score' => 'integer', 'total' => 'integer', 'passing_percent' => 'integer', 'expires_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
