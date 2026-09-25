<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'manager', 'qa_admin'], true);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', Rule::exists('assessment_categories', 'id')->where('is_active', true)],
            'difficulty' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'time_limit_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'maximum_attempts' => ['required', 'integer', 'between:1,100'],
            'available_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:available_at'],
            'publish_at' => ['nullable', 'date'],
            'allow_retake' => ['required', 'boolean'],
            'randomize_questions' => ['required', 'boolean'],
            'randomize_answers' => ['required', 'boolean'],
            'reduce_repeated_questions' => ['sometimes', 'boolean'],
            'show_correct_answers' => ['required', 'boolean'],
            'applies_to_all_campaigns' => ['sometimes', 'boolean'],
            'campaign_ids' => ['required_if:applies_to_all_campaigns,false', 'array'],
            'campaign_ids.*' => ['integer', 'distinct', Rule::exists('campaigns', 'id')->where('is_active', true)],
        ];
    }

    protected function passedValidation(): void
    {
        foreach (['available_at', 'due_at', 'publish_at'] as $field) {
            if ($this->validated($field)) {
                $this->merge([$field => Carbon::parse($this->validated($field), 'Asia/Manila')->utc()]);
            }
        }
    }
}
