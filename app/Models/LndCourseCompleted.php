<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-course completion record attached to a training referral.
 * Populated from the courses_completed array in the PMS callback payload.
 *
 * @property int         $id
 * @property int         $training_referral_id
 * @property string|null $course_code
 * @property string|null $title
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TrainingReferral $referral
 */
#[Fillable([
    'training_referral_id',
    'course_code',
    'title',
    'completed_at',
])]
class LndCourseCompleted extends Model
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrainingReferral, $this>
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(TrainingReferral::class, 'training_referral_id');
    }
}
