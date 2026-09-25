<?php

namespace App\Http\Requests;

use App\Services\EmployeeRoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
            'position' => [
                'required',
                Rule::in(array_keys(EmployeeRoleService::ROLES)),
            ],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
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
            'face_descriptor' => ['required', 'array', 'min:128', 'max:2048'],
            'face_descriptor.*' => ['required', 'numeric', 'between:-10,10'],
            'face_liveness' => ['required', 'numeric', 'between:0.5,1'],
            'face_antispoof' => ['required', 'numeric', 'between:0.5,1'],
            'face_model_version' => ['required', 'string', 'max:100'],
            'face_consent' => ['accepted'],
        ];
    }
}
