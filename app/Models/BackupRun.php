<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['file_name'];

    protected function casts(): array
    {
        return [
            'contents' => 'array',
            'encrypted_size' => 'integer',
            'database_tables' => 'integer',
            'database_rows' => 'integer',
            'file_count' => 'integer',
            'source_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'restore_tested_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
