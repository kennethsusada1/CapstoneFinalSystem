<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $model_version
 * @property string $status
 * @property string $trigger
 * @property int $source_rows
 * @property int $positive_rows
 * @property int $negative_rows
 * @property int|null $validation_rows
 * @property float|null $validation_roc_auc
 * @property array<string, mixed>|null $validation_metrics
 * @property string|null $data_signature
 * @property string|null $artifact_path
 * @property string|null $message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'model_version',
    'status',
    'trigger',
    'source_rows',
    'positive_rows',
    'negative_rows',
    'validation_rows',
    'validation_roc_auc',
    'validation_metrics',
    'data_signature',
    'artifact_path',
    'message',
    'started_at',
    'completed_at',
])]
class LnaModelTrainingRun extends Model
{
    protected function casts(): array
    {
        return [
            'source_rows' => 'integer',
            'positive_rows' => 'integer',
            'negative_rows' => 'integer',
            'validation_rows' => 'integer',
            'validation_roc_auc' => 'float',
            'validation_metrics' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
