<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_key', 'user_id', 'document_type', 'other_document_type', 'purpose', 'period_start', 'period_end', 'delivery_method', 'status', 'employee_notes', 'reviewer_notes', 'file_disk', 'file_path', 'original_filename', 'mime_type', 'file_size', 'handled_by', 'submitted_at', 'completed_at', 'released_at', 'email_sent_at', 'email_error'])]
class EmployeeDocumentRequest extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'file_size' => 'integer',
            'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'released_at' => 'datetime', 'email_sent_at' => 'datetime',
        ];
    }
}
