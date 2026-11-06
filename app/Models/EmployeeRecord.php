<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $employee_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $email
 * @property string|null $office
 * @property string|null $supervisor_employee_id
 * @property string|null $position
 * @property string|null $education_level
 * @property string|null $role_family
 * @property int|null $seniority_level
 * @property Carbon|null $hire_date
 * @property string $employment_status
 * @property string $source
 * @property Carbon|null $last_imported_at
 * @property-read int $total Aggregate alias returned by grouped report queries.
 * @property-read User|null $user
 */
#[Fillable([
    'employee_id',
    'first_name',
    'last_name',
    'middle_name',
    'email',
    'office',
    'supervisor_employee_id',
    'position',
    'education_level',
    'role_family',
    'seniority_level',
    'hire_date',
    'employment_status',
    'source',
    'last_imported_at',
])]
class EmployeeRecord extends Model
{
    protected function casts(): array
    {
        return [
            'last_imported_at' => 'datetime',
            'hire_date' => 'date',
            'seniority_level' => 'integer',
        ];
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id', 'employee_id');
    }

    /**
     * @return BelongsTo<EmployeeRecord, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_employee_id', 'employee_id');
    }

    /**
     * @return HasMany<EmployeePerformanceRecord, $this>
     */
    public function performanceRecords(): HasMany
    {
        return $this->hasMany(EmployeePerformanceRecord::class, 'employee_id', 'employee_id');
    }
}
