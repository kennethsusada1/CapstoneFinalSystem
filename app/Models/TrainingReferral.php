<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Represents a single employee training referral received from smart-pms.
 *
 * One record is created per IDP submission from PMS. The record carries a
 * full denormalised snapshot of the employee's IPCR and IDP rows so that
 * the Secretariat can display the referral reason without calling back to PMS.
 *
 * @property int         $id
 * @property string      $lnd_reference_id     e.g. "LND-REF-2026-00042"
 * @property string      $external_plan_id     e.g. "PMS-DP-42"
 * @property string      $source_system
 * @property int         $pms_user_id
 * @property int         $pms_period_id
 * @property string|null $period_name
 * @property string|null $employee_name
 * @property string|null $employee_email
 * @property string|null $employee_position
 * @property int|null    $employee_office_id
 * @property string|null $employee_office
 * @property float|null  $official_score
 * @property string|null $official_rating
 * @property array       $ipcr_snapshot
 * @property array       $idp_rows
 * @property string      $status               received|in_progress|completed
 * @property Carbon      $received_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $pms_notified_at
 * @property string|null $pms_notify_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LndCourseCompleted> $coursesCompleted
 */
#[Fillable([
    'lnd_reference_id',
    'external_plan_id',
    'source_system',
    'pms_user_id',
    'pms_period_id',
    'period_name',
    'employee_name',
    'employee_email',
    'employee_position',
    'employee_office_id',
    'employee_office',
    'official_score',
    'official_rating',
    'ipcr_snapshot',
    'idp_rows',
    'status',
    'received_at',
    'completed_at',
    'pms_notified_at',
    'pms_notify_error',
])]
class TrainingReferral extends Model
{
    protected function casts(): array
    {
        return [
            'ipcr_snapshot'    => 'array',
            'idp_rows'         => 'array',
            'received_at'      => 'datetime',
            'completed_at'     => 'datetime',
            'pms_notified_at'  => 'datetime',
            'official_score'   => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<LndCourseCompleted, $this>
     */
    public function coursesCompleted(): HasMany
    {
        return $this->hasMany(LndCourseCompleted::class);
    }
}
