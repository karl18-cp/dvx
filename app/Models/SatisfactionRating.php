<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['request_id', 'employee_id', 'rated_by', 'employee_name', 'employee_username', 'employee_role', 'reviewer_name', 'reviewer_username', 'teams', 'quality', 'productivity', 'attendance', 'communication', 'professionalism', 'average', 'comments'])]
class SatisfactionRating extends Model
{
    public const CATEGORIES = ['quality', 'productivity', 'attendance', 'communication', 'professionalism'];

    protected function casts(): array
    {
        return ['teams' => 'array', 'average' => 'decimal:2', ...array_fill_keys(self::CATEGORIES, 'integer')];
    }
}
