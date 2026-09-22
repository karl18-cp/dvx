<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCallEvaluationScorecardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'manager'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'applies_to_all_campaigns' => ['required', 'boolean'],
            'campaign_ids' => [
                Rule::requiredIf(! $this->boolean('applies_to_all_campaigns')),
                'array',
                $this->boolean('applies_to_all_campaigns') ? 'size:0' : 'min:1',
            ],
            'campaign_ids.*' => ['integer', 'distinct', Rule::exists('campaigns', 'id')->where('is_active', true)],
        ];
    }

    public function messages(): array
    {
        return [
            'campaign_ids.required' => 'Select at least one Campaign or choose All Campaigns.',
            'campaign_ids.min' => 'Select at least one Campaign or choose All Campaigns.',
            'campaign_ids.size' => 'Specific Campaigns cannot be selected with All Campaigns.',
        ];
    }
}
