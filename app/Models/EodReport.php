<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['request_id', 'user_id', 'author_name', 'author_username', 'author_role', 'report_date', 'summary', 'blockers', 'next_steps', 'task_snapshots', 'task_count'])]
class EodReport extends Model
{
    protected function casts(): array
    {
        return ['report_date' => 'date:Y-m-d', 'task_snapshots' => 'array', 'task_count' => 'integer'];
    }
}
