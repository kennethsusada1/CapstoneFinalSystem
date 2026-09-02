<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $learning_needs_analysis_id
 * @property int $rank
 * @property string $competency_name
 * @property string|null $competency_category
 * @property float $probability
 * @property string $priority
 * @property string $training_title
 * @property string $training_type
 * @property string|null $provider
 * @property string $recommendation_text
 * @property string $status
 */
#[Fillable([
    'learning_needs_analysis_id',
    'rank',
    'competency_name',
    'competency_category',
    'probability',
    'priority',
    'training_title',
    'training_type',
    'provider',
    'recommendation_text',
    'status',
])]
class LnaRecommendation extends Model
{
    /**
     * @return BelongsTo<LearningNeedsAnalysis, $this>
     */
    public function learningNeedsAnalysis(): BelongsTo
    {
        return $this->belongsTo(LearningNeedsAnalysis::class);
    }
}
