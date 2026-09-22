<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'position' => ['required', Rule::in([
                'Admin',
                'Team Leader',
                'Agent',
                'IT Admin',
                'IT Support',
                'IT Developer',
            ])],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('employee')),
            ],
            'status' => ['required', Rule::in([
                'active',
                'suspended',
                'floating',
                'resigned',
                'terminated',
            ])],
            'new_password' => ['nullable', Password::min(8)],
            'birth_date' => ['required', 'date', 'before:today'],
            'start_date' => ['required', 'date'],
            'gender' => ['required', 'string', 'max:50'],
            'civil_status' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_relationship' => ['required', 'string', 'max:100'],
            'emergency_contact_phone' => ['required', 'string', 'max:30'],
            'emergency_contact_address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
