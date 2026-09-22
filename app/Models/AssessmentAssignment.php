<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'employee_id', 'campaign_id', 'campaign_name', 'source_team_id', 'assigned_by', 'assigned_at', 'available_from', 'due_date', 'status'])]
class AssessmentAssignment extends Model
{
    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'available_from' => 'datetime', 'due_date' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'source_team_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class, 'assignment_id');
    }

    public function effectiveAvailableAt(): ?CarbonInterface
    {
        return $this->available_from ?? $this->assessment->available_at;
    }

    public function effectiveDueAt(): ?CarbonInterface
    {
        return $this->due_date ?? $this->assessment->due_at;
    }
}
