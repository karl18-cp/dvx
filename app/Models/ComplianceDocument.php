<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['file_disk', 'file_path'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date:Y-m-d',
            'expires_at' => 'date:Y-m-d',
            'remind_days_before' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
