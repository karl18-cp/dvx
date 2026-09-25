<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string|null $username
 * @property string $name
 * @property string $email
 * @property string $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['username', 'name', 'email', 'role', 'status', 'team', 'schedule', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'profile_photo_path'])]
class User extends Authenticatable implements PasskeyUser
{
    protected $appends = ['avatar'];

    public function getAvatarAttribute(): string
    {
        return $this->profile_photo_path
            ? route('profile-photo.show', ['user' => $this->id, 'v' => substr(hash('sha256', $this->profile_photo_path), 0, 12)], false)
            : '';
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return HasOne<PersonalInformation, $this>
     */
    public function personalInformation(): HasOne
    {
        return $this->hasOne(PersonalInformation::class);
    }

    /**
     * @return HasOne<FaceCredential, $this>
     */
    public function faceCredential(): HasOne
    {
        return $this->hasOne(FaceCredential::class);
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** @return HasMany<UndertimeRequest, $this> */
    public function undertimeRequests(): HasMany
    {
        return $this->hasMany(UndertimeRequest::class);
    }

    /** @return HasMany<OvertimeRequest, $this> */
    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function teamMembership(): HasOne
    {
        return $this->hasOne(TeamMember::class);
    }

    public function campaignSchedule(): BelongsTo
    {
        return $this->belongsTo(CampaignSchedule::class);
    }

    /** @return HasMany<TeamLeaderAssignment, $this> */
    public function ledTeamAssignments(): HasMany
    {
        return $this->hasMany(TeamLeaderAssignment::class);
    }

    public function callEvaluations(): HasMany
    {
        return $this->hasMany(CallEvaluation::class, 'employee_id');
    }

    public function evaluatedCalls(): HasMany
    {
        return $this->hasMany(CallEvaluation::class, 'evaluator_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
