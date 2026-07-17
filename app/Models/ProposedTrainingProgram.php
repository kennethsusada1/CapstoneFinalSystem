<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learning_development_plan_id
 * @property string $title
 * @property string $status
 * @property string|null $review_remarks
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property-read LearningDevelopmentPlan $plan
 * @property-read User|null $reviewer
 */
#[Fillable([
    'learning_development_plan_id',
    'title',
    'status',
    'review_remarks',
    'reviewed_by',
    'reviewed_at',
])]
class ProposedTrainingProgram extends Model
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LearningDevelopmentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(LearningDevelopmentPlan::class, 'learning_development_plan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
