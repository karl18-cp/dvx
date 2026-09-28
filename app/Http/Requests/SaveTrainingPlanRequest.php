<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTrainingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && in_array($this->user()?->role, ['admin', 'qa_admin'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'phases' => ['required', 'array', 'size:3'],
            'phases.*' => ['required', 'array:days,rate'],
            'phases.*.days' => ['required', 'integer', 'between:0,180'],
            'phases.*.rate' => ['required', 'regex:/^\d{1,6}(\.\d{1,2})?$/'],
            'first_allowance_day' => ['required', 'integer', 'between:1,366'],
            'allowance_basis' => ['required', Rule::in(['attended'])],
            'trainee_ids' => ['present', 'array', 'max:500'],
            'trainee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $days = array_sum(array_column($this->input('phases'), 'days'));
            if ($days < 1 || $days > 366) {
                $validator->errors()->add('phases', 'A plan must contain between 1 and 366 training days.');
            }
            if ($this->integer('first_allowance_day') > $days) {
                $validator->errors()->add('first_allowance_day', 'The first allowance day cannot exceed the total training days.');
            }
        }];
    }
}
