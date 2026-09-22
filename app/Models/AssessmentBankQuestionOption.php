<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bank_question_id', 'option_text', 'is_correct', 'display_order'])]
class AssessmentBankQuestionOption extends Model
{
    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
