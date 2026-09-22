<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question_id', 'option_text', 'is_correct', 'display_order'])]
class AssessmentQuestionOption extends Model
{
    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
