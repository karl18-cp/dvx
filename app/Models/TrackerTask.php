<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['request_id', 'created_by', 'creator_name', 'title', 'description', 'checklist'])]
class TrackerTask extends Model
{
    use SoftDeletes;

    public function assignments(): HasMany
    {
        return $this->hasMany(TrackerTaskAssignment::class);
    }

    protected function casts(): array
    {
        return ['checklist' => 'array'];
    }
}
