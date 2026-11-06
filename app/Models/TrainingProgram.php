<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $category
 * @property string|null $description
 * @property string|null $provider
 * @property string $training_type
 * @property string $delivery_mode
 * @property string|null $venue
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int $slots
 * @property-read int $claimed_slots Count alias returned by enrollment queries.
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable([
    'title',
    'category',
    'description',
    'provider',
    'training_type',
    'delivery_mode',
    'venue',
    'start_date',
    'end_date',
    'slots',
    'status',
    'created_by',
])]
class TrainingProgram extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Limit programs to published schedules that have not already ended.
     * A missing schedule remains available so employees can apply while details are being finalized.
     *
     * @param  Builder<TrainingProgram>  $query
     * @return Builder<TrainingProgram>
     */
    public function scopeOpenForEnrollment(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('status', 'published')
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->whereDate('end_date', '>=', $today)
                    ->orWhere(function (Builder $query) use ($today): void {
                        $query->whereNull('end_date')
                            ->where(fn (Builder $query) => $query->whereNull('start_date')->orWhereDate('start_date', '>=', $today));
                    });
            });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TrainingApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(TrainingApplication::class);
    }
}
