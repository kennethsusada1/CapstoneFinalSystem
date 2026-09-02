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
 * @property int $user_id
 * @property string|null $employee_id
 * @property string|null $ipcr_rating
 * @property array<int, string>|null $core_functions
 * @property array<int, string>|null $support_functions
 * @property array<string, string>|null $skill_assessments
 * @property array<string, string>|null $supervisor_skill_assessments
 * @property array<int, string>|null $preferred_learning_methods
 * @property string|null $preferred_learning_methods_other
 * @property array<int, string>|null $assessment_methods
 * @property array<int, string>|null $supervisor_assessment_methods
 * @property string|null $employee_signature
 * @property string|null $supervisor_signature
 * @property Carbon|null $supervisor_signed_on
 * @property string $focus_area
 * @property string $competency_gap
 * @property string|null $predictive_skills_gap
 * @property string|null $prescriptive_training_recommendation
 * @property bool|null $training_needed
 * @property float|null $training_need_probability
 * @property string|null $analytics_model_version
 * @property string $proposed_intervention
 * @property string $priority_level
 * @property string $status
 * @property string|null $review_remarks
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $analytics_generated_at
 * @property Carbon|null $submitted_on
 * @property-read User $user
 * @property-read User|null $reviewer
 * @property-read Collection<int, TrainingApplication> $trainingApplications
 */
#[Fillable([
    'user_id',
    'employee_id',
    'ipcr_rating',
    'core_functions',
    'support_functions',
    'skill_assessments',
    'supervisor_skill_assessments',
    'preferred_learning_methods',
    'preferred_learning_methods_other',
    'assessment_methods',
    'supervisor_assessment_methods',
    'employee_signature',
    'supervisor_signature',
    'supervisor_signed_on',
    'focus_area',
    'competency_gap',
    'predictive_skills_gap',
    'prescriptive_training_recommendation',
    'training_needed',
    'training_need_probability',
    'analytics_model_version',
    'proposed_intervention',
    'priority_level',
    'status',
    'review_remarks',
    'reviewed_by',
    'reviewed_at',
    'analytics_generated_at',
    'submitted_on',
])]
class LearningNeedsAnalysis extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_on' => 'date',
            'reviewed_at' => 'datetime',
            'analytics_generated_at' => 'datetime',
            'training_needed' => 'boolean',
            'training_need_probability' => 'decimal:4',
            'core_functions' => 'array',
            'support_functions' => 'array',
            'skill_assessments' => 'array',
            'supervisor_skill_assessments' => 'array',
            'preferred_learning_methods' => 'array',
            'assessment_methods' => 'array',
            'supervisor_assessment_methods' => 'array',
            'supervisor_signed_on' => 'date',
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

    /**
     * @return HasMany<TrainingApplication, $this>
     */
    public function trainingApplications(): HasMany
    {
        return $this->hasMany(TrainingApplication::class);
    }

    /**
     * @return HasMany<LnaRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(LnaRecommendation::class);
    }
}
