<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['request_id', 'sanction_id', 'employee_id', 'issued_by', 'sanction_name', 'sanction_description', 'employee_name', 'employee_username', 'employee_role', 'issuer_name', 'punishment', 'notes'])]
class EmployeeSanction extends Model
{
    public const PUNISHMENTS = [
        'Documented Verbal',
        'First Written',
        'Final Written',
        'Suspension',
        'Subject for Suspension/Termination',
    ];
}
