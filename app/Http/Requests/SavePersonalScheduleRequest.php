<?php

namespace App\Http\Requests;

class SavePersonalScheduleRequest extends SaveCampaignScheduleRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'team_leader' && $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['name']);

        return $rules;
    }
}
