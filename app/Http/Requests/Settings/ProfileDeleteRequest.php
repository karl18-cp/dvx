<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\AccountingEntry;
use App\Models\AccountingExpense;
use App\Models\EmployeeContract;
use App\Models\EmployeeBackpay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return ! $user->source_trainee_id && ! $user->employeeAccount()->exists()
            && ! AccountingEntry::query()->where(function ($query) use ($user) {
                foreach (['user_id', 'created_by', 'approved_by', 'paid_by', 'voided_by'] as $column) {
                    $query->orWhere($column, $user->id);
                }
            })->exists()
            && ! EmployeeContract::query()->where(function ($query) use ($user) {
                foreach (['user_id', 'created_by', 'updated_by', 'archived_by'] as $column) {
                    $query->orWhere($column, $user->id);
                }
            })->exists()
            && ! EmployeeBackpay::query()->where(function ($query) use ($user) {
                foreach (['user_id', 'created_by', 'updated_by'] as $column) {
                    $query->orWhere($column, $user->id);
                }
            })->exists()
            && ! AccountingExpense::where('created_by', $user->id)->orWhere('voided_by', $user->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
