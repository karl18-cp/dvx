<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'separation_date', 'separation_type', 'status', 'property_status', 'property_items', 'property_notes', 'final_attendance_status', 'final_attendance_record_id', 'final_attendance_notes', 'outstanding_status', 'outstanding_amount_cents', 'outstanding_notes', 'quitclaim_status', 'quitclaim_contract_id', 'quitclaim_notes', 'exit_interview_status', 'exit_interview_at', 'exit_interview_notes', 'created_by', 'updated_by', 'approved_by', 'approved_at', 'approval_notes'])]
class EmployeeClearance extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function backpay(): ?EmployeeBackpay
    {
        return EmployeeBackpay::query()->where('user_id', $this->user_id)->latest('separation_date')->first();
    }

    public function quitclaim(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'quitclaim_contract_id');
    }

    public function finalAttendance(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'final_attendance_record_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'separation_date' => 'date',
            'property_items' => 'array',
            'outstanding_amount_cents' => 'integer',
            'exit_interview_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
