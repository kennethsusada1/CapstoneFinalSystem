<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $employee_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $email
 * @property string|null $office
 * @property string|null $position
 * @property string $employment_status
 * @property string $source
 * @property-read User|null $user
 */
#[Fillable([
    'employee_id',
    'first_name',
    'last_name',
    'middle_name',
    'email',
    'office',
    'position',
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
        ];
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id', 'employee_id');
    }
}
