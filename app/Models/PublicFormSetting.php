<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicFormSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['definition' => 'array'];
    }
}
