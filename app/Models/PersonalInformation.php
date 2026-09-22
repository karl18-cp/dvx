<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property Carbon $birth_date
 * @property Carbon $start_date
 * @property string $gender
 * @property string $civil_status
 * @property string $phone
 * @property string $address
 * @property string $emergency_contact_name
 * @property string $emergency_contact_relationship
 * @property string $emergency_contact_phone
 * @property string|null $emergency_contact_address
 */
#[Fillable([
    'user_id',
    'email',
    'birth_date',
    'start_date',
    'gender',
    'civil_status',
    'phone',
    'address',
    'emergency_contact_name',
    'emergency_contact_relationship',
    'emergency_contact_phone',
    'emergency_contact_address',
])]
class PersonalInformation extends Model
{
    protected $table = 'personal_information';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'start_date' => 'date',
        ];
    }
}
