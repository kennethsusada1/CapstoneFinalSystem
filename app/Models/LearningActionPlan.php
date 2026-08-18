<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $training_application_id
 * @property string|null $employee_id
 * @property string $training_title
 * @property string $implementation_summary
 * @property string|null $learning_outcomes
 * @property string $status
 * @property string $receipt_status
 * @property string|null $receipt_remarks
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property Carbon|null $submitted_on
 * @property-read User $user
 * @property-read TrainingApplication|null $trainingApplication
 */
#[Fillable([
    'user_id',
    'training_application_id',
    'employee_id',
    'training_title',
    'implementation_summary',
    'learning_outcomes',
    'status',
    'receipt_status',
    'receipt_remarks',
    'received_by',
    'received_at',
    'submitted_on',
])]
class LearningActionPlan extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_on' => 'date',
            'received_at' => 'datetime',
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
     * @return BelongsTo<TrainingApplication, $this>
     */
    public function trainingApplication(): BelongsTo
    {
        return $this->belongsTo(TrainingApplication::class);
    }
}
