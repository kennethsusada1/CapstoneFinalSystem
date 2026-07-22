<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $submitted_by
 * @property int|null $training_application_id
 * @property string $title
 * @property string $planning_year
 * @property string $objectives
 * @property string $priority_programs
 * @property string|null $budget_notes
 * @property string $status
 * @property string $review_status
 * @property string|null $review_remarks
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $submitted_at
 * @property-read User $submitter
 * @property-read TrainingApplication|null $trainingApplication
 * @property-read Collection<int, ProposedTrainingProgram> $programs
 */
#[Fillable([
    'submitted_by',
    'training_application_id',
    'title',
    'planning_year',
    'objectives',
    'priority_programs',
    'budget_notes',
    'status',
    'review_status',
    'review_remarks',
    'reviewed_by',
    'reviewed_at',
    'submitted_at',
])]
class LearningDevelopmentPlan extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<TrainingApplication, $this>
     */
    public function trainingApplication(): BelongsTo
    {
        return $this->belongsTo(TrainingApplication::class);
    }

    /**
     * @return HasMany<ProposedTrainingProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(ProposedTrainingProgram::class);
    }
}
