<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $employee_id
 * @property string $focus_area
 * @property string $competency_gap
 * @property string $proposed_intervention
 * @property string $priority_level
 * @property string $status
 * @property string|null $review_remarks
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $submitted_on
 * @property-read User $user
 * @property-read User|null $reviewer
 */
#[Fillable([
    'user_id',
    'employee_id',
    'focus_area',
    'competency_gap',
    'proposed_intervention',
    'priority_level',
    'status',
    'review_remarks',
    'reviewed_by',
    'reviewed_at',
    'submitted_on',
])]
class LearningNeedsAnalysis extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_on' => 'date',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
