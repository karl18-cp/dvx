<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['assessment_id', 'skill_id', 'category_id', 'difficulty', 'question_type', 'questions_to_select', 'points_per_question', 'display_order'])]
class AssessmentRandomPool extends Model {}
