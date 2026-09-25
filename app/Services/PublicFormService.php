<?php

namespace App\Services;

use App\Http\Controllers\BusinessInquiryController;
use App\Models\PublicFormSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicFormService
{
    public function defaults(string $kind): array
    {
        $field = fn ($id, $label, $type = 'text', $required = true, $options = []) => compact('id', 'label', 'type', 'required', 'options') + ['visible' => true, 'placeholder' => '', 'custom' => false, 'locked' => $required];

        return $kind === 'application' ? [
            'title' => 'Apply to join our team', 'description' => 'Complete the form and share your experience with our recruitment team.', 'submit_label' => 'Submit Application',
            'fields' => [
                $field('first_name', 'First Name'), $field('last_name', 'Last Name'), $field('email', 'Email Address', 'email'), $field('phone', 'Mobile Number', 'tel'),
                $field('position', 'Position', 'select', true, ['Customer Service Representative', 'Other']), $field('location', 'Current City / Province', 'text', false),
                $field('years_experience', 'Years of Relevant Experience', 'number'), $field('resume', 'Résumé (PDF, DOC, DOCX — max 5 MB)', 'file', false),
                $field('message', 'Short Introduction / Relevant Experience', 'textarea', false),
            ],
        ] : [
            'title' => 'Request a meeting', 'description' => 'Let’s explore what we can build together.', 'submit_label' => 'Send meeting request',
            'fields' => [
                $field('name', 'Full name'), $field('company', 'Company'), $field('email', 'Work email', 'email'), $field('phone', 'Phone (optional)', 'tel', false),
                $field('service', 'Service of interest', 'select', true, BusinessInquiryController::SERVICES),
                $field('meeting_time', 'Preferred meeting date and time', 'datetime-local'), $field('timezone', 'Meeting timezone', 'timezone'),
                $field('message', 'How can we help your business?', 'textarea'),
            ],
        ];
    }

    public function definition(string $kind): array
    {
        return PublicFormSetting::where('kind', $kind)->value('definition') ?? $this->defaults($kind);
    }

    public function validateDefinition(Request $request, string $kind): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:500'], 'submit_label' => ['required', 'string', 'max:50'],
            'fields' => ['required', 'array', 'max:30'], 'fields.*.id' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:150'], 'fields.*.placeholder' => ['nullable', 'string', 'max:200'],
            'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'email', 'tel', 'number', 'date', 'select', 'checkbox', 'file', 'datetime-local', 'timezone'])],
            'fields.*.required' => ['required', 'boolean'], 'fields.*.visible' => ['required', 'boolean'],
            'fields.*.options' => ['present', 'array', 'max:50'], 'fields.*.options.*' => ['required', 'string', 'max:100'],
        ]);
        $base = collect($this->defaults($kind)['fields'])->keyBy('id');
        foreach ($base as $id => $field) {
            if (! collect($data['fields'])->contains('id', $id)) {
                throw ValidationException::withMessages(['fields' => 'Built-in fields cannot be deleted. Hide optional fields instead.']);
            }
        }
        foreach ($data['fields'] as $i => &$field) {
            $original = $base->get($field['id']);
            if ($original && ($field['type'] !== $original['type'] || ($original['locked'] && (! $field['visible'] || ! $field['required'])))) {
                throw ValidationException::withMessages(["fields.$i.label" => 'Core field type, visibility and required status must be preserved.']);
            }
            if (! $original && (! str_starts_with($field['id'], 'custom_') || ! in_array($field['type'], ['text', 'textarea', 'email', 'number', 'date', 'select', 'checkbox'], true))) {
                throw ValidationException::withMessages(["fields.$i.label" => 'Extra questions must use a supported type and a unique custom ID.']);
            }
            if ($field['type'] === 'select' && ! count($field['options'])) {
                throw ValidationException::withMessages(["fields.$i.options" => 'Add at least one option.']);
            }
            if (count(array_unique($field['options'])) !== count($field['options'])) {
                throw ValidationException::withMessages(["fields.$i.options" => 'Options within a dropdown must be unique.']);
            }
            $field['custom'] = ! $original;
            $field['locked'] = $original['locked'] ?? false;
            $field['placeholder'] = $field['placeholder'] ?? '';
        }
        unset($field);

        return $data;
    }

    public function submission(Request $request, string $kind, array $rules): array
    {
        $fields = $this->definition($kind)['fields'];
        $attributes = [];
        foreach ($fields as $field) {
            $key = $field['custom'] ? 'custom_fields.'.$field['id'] : $field['id'];
            $attributes[$key] = $field['label'];
            if (! $field['visible']) {
                $rules[$key] = ['exclude'];

                continue;
            }
            if ($field['custom']) {
                $rules[$key] = match ($field['type']) {
                    'email' => ['email:rfc', 'max:255'], 'number' => ['numeric', 'between:-1000000000,1000000000'],
                    'date' => ['date_format:Y-m-d'], 'select' => ['string', Rule::in($field['options'])],
                    'checkbox' => [$field['required'] ? 'accepted' : 'boolean'], default => ['string', 'max:3000'],
                };
            }
            $rules[$key] = array_values(array_filter($rules[$key] ?? [], fn ($rule) => ! in_array($rule, ['required', 'nullable'], true)));
            array_unshift($rules[$key], $field['required'] ? 'required' : 'nullable');
            if ($field['type'] === 'select') {
                $rules[$key] = [$field['required'] ? 'required' : 'nullable', 'string', Rule::in($field['options'])];
            }
        }
        $rules['custom_fields'] = ['sometimes', 'array'];
        $validated = Validator::make($request->all(), $rules, [], $attributes)->validate();
        $answers = [];
        foreach ($fields as $field) {
            if ($field['custom'] && $field['visible']) {
                $value = data_get($validated, 'custom_fields.'.$field['id']);
                if ($field['type'] === 'checkbox') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                $answers[] = ['id' => $field['id'], 'label' => $field['label'], 'type' => $field['type'], 'value' => $value];
            }
        }
        unset($validated['custom_fields']);

        return [...$validated, 'custom_answers' => $answers];
    }
}
