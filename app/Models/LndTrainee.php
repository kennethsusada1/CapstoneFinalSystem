<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cross-system identity map between smart-pms users and L&D users.
 * One record per employee. Upserted on every intake from PMS.
 *
 * lnd_user_id is null at intake and is set manually by Secretariat
 * when the employee's L&D account is activated.
 *
 * @property int         $id
 * @property int         $pms_user_id      Stable FK to smart-pms users.id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $position
 * @property string|null $office_name
 * @property int|null    $lnd_user_id      FK to L&D users.id — set after account activation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $lndUser
 */
#[Fillable([
    'pms_user_id',
    'name',
    'email',
    'position',
    'office_name',
    'lnd_user_id',
])]
class LndTrainee extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function lndUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lnd_user_id');
    }
}
