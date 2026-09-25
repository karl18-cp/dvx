<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'description', 'status', 'ranking_enabled', 'fields', 'revision', 'created_by'])]
class EmployeeForm extends Model
{
    use SoftDeletes;

    public const TYPES = ['text', 'textarea', 'number', 'email', 'date', 'dropdown', 'radio', 'checkbox'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'ranking_enabled' => 'boolean'];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'employee_form_team');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EmployeeFormResponse::class);
    }
}
