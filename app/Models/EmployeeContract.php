<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeContract extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['request_key', 'file_disk', 'file_path'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'file_size' => 'integer',
            'archived_at' => 'datetime',
            'version' => 'integer',
            'issued_at' => 'datetime',
            'signature_due_at' => 'datetime',
            'immutable_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function signature(): HasOne
    {
        return $this->hasOne(EmployeeContractSignature::class);
    }
}
