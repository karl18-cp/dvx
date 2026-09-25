<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCampaignScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'manager'], true);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('campaign_schedules', 'name')->ignore($this->route('schedule'))],
            'days' => ['required', 'array', 'size:7'],
            'days.*' => ['required', 'array:day,no_schedule,time_in,time_out,break_start,break_end'],
            'days.*.day' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.no_schedule' => ['required', 'boolean'],
            'days.*.time_in' => ['nullable', 'date_format:H:i'],
            'days.*.time_out' => ['nullable', 'date_format:H:i'],
            'days.*.break_start' => ['nullable', 'date_format:H:i'],
            'days.*.break_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $shifts = [];
            foreach ($this->input('days') as $index => $day) {
                if ($day['no_schedule']) {
                    continue;
                }
                foreach (['time_in', 'time_out'] as $field) {
                    if (empty($day[$field])) {
                        $validator->errors()->add("days.$index.$field", 'Enter a time for this scheduled day.');
                    }
                }
                if (empty($day['time_in']) || empty($day['time_out'])) {
                    continue;
                }
                $start = $this->minutes($day['time_in']);
                $duration = ($this->minutes($day['time_out']) - $start + 1440) % 1440;
                if ($duration === 0) {
                    $validator->errors()->add("days.$index.time_out", 'Time out must differ from time in.');
                }
                $shifts[$day['day']] = ['start' => $start, 'end' => $start + $duration, 'index' => $index];
                $breakStart = $day['break_start'] ?? null;
                $breakEnd = $day['break_end'] ?? null;
                if ((bool) $breakStart !== (bool) $breakEnd) {
                    $validator->errors()->add("days.$index.break_end", 'Enter both break times, or leave both blank.');
                } elseif ($breakStart && $breakEnd) {
                    $offset = ($this->minutes($breakStart) - $start + 1440) % 1440;
                    $breakDuration = ($this->minutes($breakEnd) - $this->minutes($breakStart) + 1440) % 1440;
                    if ($breakDuration === 0 || $offset + $breakDuration > $duration) {
                        $validator->errors()->add("days.$index.break_end", 'The break must end after it starts and fit within the shift.');
                    }
                }
            }
            foreach ($shifts as $day => $shift) {
                $next = $shifts[$day === 7 ? 1 : $day + 1] ?? null;
                if ($next && $shift['end'] > 1440 + $next['start']) {
                    $validator->errors()->add('days.'.$shift['index'].'.time_out', 'This overnight shift overlaps the next scheduled day.');
                }
            }
        }];
    }

    public function scheduleDays(): array
    {
        return array_map(function (array $day): array {
            foreach (['time_in', 'time_out', 'break_start', 'break_end'] as $field) {
                $day[$field] = $day['no_schedule'] ? null : ($day[$field] ?? null);
            }

            return $day;
        }, $this->validated('days'));
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
