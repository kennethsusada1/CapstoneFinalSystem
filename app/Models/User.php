<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string $email
 * @property string|null $employee_id
 * @property int|null    $pms_user_id
 * @property string|null $office
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $activation_sent_at
 * @property string|null $activation_token
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EmployeeRecord|null $employeeRecord
 */
#[Fillable(['name', 'address', 'email', 'employee_id', 'office', 'pms_user_id', 'password', 'activation_sent_at', 'activation_token'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activation_sent_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LearningNeedsAnalysis, $this>
     */
    public function learningNeedsAnalyses(): HasMany
    {
        return $this->hasMany(LearningNeedsAnalysis::class);
    }

    /**
     * @return HasMany<TrainingApplication, $this>
     */
    public function trainingApplications(): HasMany
    {
        return $this->hasMany(TrainingApplication::class);
    }

    /**
     * @return HasMany<LearningActionPlan, $this>
     */
    public function learningActionPlans(): HasMany
    {
        return $this->hasMany(LearningActionPlan::class);
    }

    /**
     * @return HasMany<LearningDevelopmentPlan, $this>
     */
    public function learningDevelopmentPlans(): HasMany
    {
        return $this->hasMany(LearningDevelopmentPlan::class, 'submitted_by');
    }

    /**
     * @return HasOne<EmployeeRecord, $this>
     */
    public function employeeRecord(): HasOne
    {
        return $this->hasOne(EmployeeRecord::class, 'employee_id', 'employee_id');
    }

    /**
     * The L&D trainee identity-map record for this user (when this user is a PMS-referred employee).
     *
     * @return HasOne<LndTrainee, $this>
     */
    public function lndTrainee(): HasOne
    {
        return $this->hasOne(LndTrainee::class, 'lnd_user_id');
    }
}
