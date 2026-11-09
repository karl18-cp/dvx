<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_contract_id', 'signer_id', 'signer_name', 'signer_username', 'signer_email', 'signer_role', 'signature_name', 'consent_text', 'document_sha256', 'signature_hash', 'ip_address', 'user_agent', 'signed_at'])]
class EmployeeContractSignature extends Model
{
    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'employee_contract_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }
}
