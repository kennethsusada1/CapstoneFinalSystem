<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $learning_needs_analysis_id
 * @property int|null $training_referral_id
 * @property string|null $employee_id
 * @property string $training_title
 * @property string $training_type
 * @property string|null $provider
 * @property string|null $office
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int $progress_percent
 * @property string $status
 * @property string $secretariat_status
 * @property string|null $process_remarks
 * @property int|null $processed_by
 * @property Carbon|null $processed_at
 * @property bool $is_attended
 * @property Carbon|null $completed_on
 * @property-read User $user
 * @property-read LearningNeedsAnalysis|null $learningNeedsAnalysis
 * @property-read LearningDevelopmentPlan|null $learningDevelopmentPlan
 * @property-read TrainingReferral|null $trainingReferral
 */
#[Fillable([
    'user_id',
    'learning_needs_analysis_id',
    'training_referral_id',
    'employee_id',
    'training_title',
    'training_type',
    'provider',
    'office',
    'start_date',
    'end_date',
    'progress_percent',
    'status',
    'secretariat_status',
    'process_remarks',
    'processed_by',
    'processed_at',
    'is_attended',
    'completed_on',
])]
class TrainingApplication extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_on' => 'date',
            'processed_at' => 'datetime',
            'is_attended' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LearningNeedsAnalysis, $this>
     */
    public function learningNeedsAnalysis(): BelongsTo
    {
        return $this->belongsTo(LearningNeedsAnalysis::class);
    }

    /**
     * @return HasOne<LearningDevelopmentPlan, $this>
     */
    public function learningDevelopmentPlan(): HasOne
    {
        return $this->hasOne(LearningDevelopmentPlan::class);
    }

    /**
     * @return BelongsTo<TrainingReferral, $this>
     */
    public function trainingReferral(): BelongsTo
    {
        return $this->belongsTo(TrainingReferral::class);
    }
}
