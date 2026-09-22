<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'skill_id', 'points_earned', 'points_possible', 'percentage'])]
class AssessmentSkillResult extends Model
{
    protected function casts(): array { return ['points_earned' => 'decimal:2', 'points_possible' => 'decimal:2', 'percentage' => 'decimal:2']; }
    public function attempt(): BelongsTo { return $this->belongsTo(AssessmentAttempt::class); }
    public function skill(): BelongsTo { return $this->belongsTo(AssessmentSkill::class); }
}
