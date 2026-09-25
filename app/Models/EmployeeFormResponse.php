<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['request_id', 'employee_form_id', 'employee_id', 'employee_name', 'employee_username', 'form_title', 'form_description', 'revision', 'ranking_enabled', 'teams', 'answers', 'points'])]
class EmployeeFormResponse extends Model
{
    protected function casts(): array
    {
        return ['teams' => 'array', 'answers' => 'array', 'ranking_enabled' => 'boolean'];
    }
}
