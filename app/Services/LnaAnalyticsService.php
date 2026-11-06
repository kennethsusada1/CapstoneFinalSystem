<?php

namespace App\Services;

use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class LnaAnalyticsService
{
    /** @var array<string, mixed>|null */
    private ?array $model = null;

    private bool $modelLoaded = false;

    public function __construct(
        private readonly StaticLnaAnalyticsService $staticFallback,
    ) {}

    /**
     * Generate model-backed analytics for a supervisor-reviewed LNA.
     *
     * @return array{predictive_skills_gap: string, prescriptive_training_recommendation: string|null, training_needed: bool|null, training_need_probability: float|null, analytics_model_version: string, recommendations: array<int, array<string, mixed>>}
     */
    public function generate(LearningNeedsAnalysis $entry): array
    {
        $model = $this->model();

        if ($model === null) {
            return $this->fallbackAnalytics($entry);
        }

        $entry->loadMissing('user.employeeRecord');
        $employeeRatings = collect($entry->skill_assessments ?? []);
        $supervisorRatings = collect($entry->supervisor_skill_assessments ?? []);
        $fallback = $this->staticFallback->generate($entry);
        $employeeRecord = $entry->user->employeeRecord;
        $asOf = $entry->submitted_on ?? $entry->created_at ?? now();
        $trainingCount = TrainingApplication::query()
            ->where('user_id', $entry->user_id)
            ->where(fn ($query) => $query->where('status', 'completed')->orWhere('is_attended', true))
            ->where(fn ($query) => $query->whereBetween('completed_on', [
                $asOf->copy()->subYears(3)->toDateString(),
                $asOf->toDateString(),
            ])->orWhere(fn ($legacy) => $legacy->whereNull('completed_on')->whereBetween('created_at', [
                $asOf->copy()->subYears(3),
                $asOf,
            ])))
            ->count();
        $ipcrRatings = $employeeRecord?->performanceRecords
            ?->whereBetween('review_year', [$asOf->year - 3, $asOf->year])
            ->pluck('ipcr_rating')
            ->filter(fn ($rating): bool => is_numeric($rating)) ?? collect();
        $ipcrRating = $ipcrRatings->isNotEmpty()
            ? round((float) $ipcrRatings->avg(), 2)
            : $this->numeric($entry->ipcr_rating);
        $yearsOfService = $employeeRecord?->hire_date === null
            ? null
            : max(0, $employeeRecord->hire_date->diffInYears($asOf));
        $recommendationCatalog = $model['recommendation_catalog'] ?? [];
        $threshold = (float) ($model['feature_schema']['threshold'] ?? 0.5);

        $predictions = $employeeRatings
            ->map(function (mixed $employeeRating, string $skill) use ($model, $supervisorRatings, $trainingCount, $recommendationCatalog, $threshold, $fallback, $employeeRecord, $ipcrRating, $yearsOfService): ?array {
                $employeeScore = $this->rating($employeeRating);

                if ($employeeScore === null) {
                    return null;
                }

                $supervisorScore = $this->rating($supervisorRatings->get($skill));
                $supervisorScore ??= $employeeScore;
                $catalog = $recommendationCatalog[strtolower(trim($skill))] ?? [];
                $requiredLevel = (float) ($catalog['required_level'] ?? 3);
                $skillGap = max(0, $requiredLevel - $supervisorScore);
                $probability = $this->predict($model, [
                    'employee_assessment' => $employeeScore,
                    'required_level' => $requiredLevel,
                    'ipcr_rating' => $ipcrRating,
                    'trainings_last_3_years' => $trainingCount,
                    'years_of_service' => $yearsOfService,
                    'seniority_level' => $employeeRecord->seniority_level ?? $this->inferSeniority($employeeRecord?->position),
                    'role_family' => $employeeRecord?->role_family ?: $this->inferRoleFamily($employeeRecord?->position),
                    'education_level' => (string) ($employeeRecord->education_level ?? ''),
                    'employment_status' => (string) ($employeeRecord->employment_status ?? ''),
                    'competency_category' => (string) ($catalog['competency_category'] ?? ''),
                ]);
                $trainingTitle = (string) ($catalog['training_title'] ?? $fallback['prescriptive_training_recommendation']);
                $category = (string) ($catalog['competency_category'] ?? 'LNA skill development');
                $priority = $probability >= 0.75 ? 'high' : ($probability >= $threshold ? 'medium' : 'low');
                $trainingMetadata = $this->trainingMetadata($trainingTitle);

                return [
                    'competency_name' => $skill,
                    'competency_category' => $category,
                    'probability' => round($probability, 4),
                    'priority' => $priority,
                    'training_title' => $trainingTitle,
                    'training_type' => $trainingMetadata['training_type'],
                    'provider' => $trainingMetadata['provider'],
                    'recommendation_text' => sprintf(
                        '%s has a predicted training-need probability of %.1f%% with an assessed competency gap of %.1f.',
                        $skill,
                        $probability * 100,
                        $skillGap,
                    ),
                    'skill_gap' => round($skillGap, 2),
                ];
            })
            ->filter()
            ->sortByDesc('probability')
            ->values();

        if ($predictions->isEmpty()) {
            return $this->fallbackAnalytics($entry);
        }

        $overallProbability = (float) $predictions->max('probability');
        $recommendations = $predictions
            ->filter(fn (array $prediction): bool => $prediction['probability'] >= $threshold)
            ->take(5)
            ->values()
            ->map(function (array $prediction, int $index): array {
                unset($prediction['skill_gap']);
                $prediction['rank'] = $index + 1;

                return $prediction;
            })
            ->all();
        $trainingNeeded = $overallProbability >= $threshold;
        $topRecommendation = $recommendations[0] ?? null;
        $assessedGaps = $employeeRatings
            ->filter(fn (mixed $rating): bool => ($score = $this->rating($rating)) !== null && $score < 3)
            ->keys()
            ->merge($supervisorRatings
                ->filter(fn (mixed $rating): bool => ($score = $this->rating($rating)) !== null && $score < 3)
                ->keys());
        $gapSummary = collect($recommendations)
            ->pluck('competency_name')
            ->merge($assessedGaps)
            ->unique()
            ->sort()
            ->values()
            ->implode(', ');
        if ($gapSummary === '') {
            $gapSummary = $recommendations === []
                ? 'No immediate training need predicted from the reviewed assessment.'
                : collect($recommendations)->pluck('competency_name')->implode(', ');
        }

        return [
            'predictive_skills_gap' => $gapSummary,
            'prescriptive_training_recommendation' => $topRecommendation['training_title'] ?? null,
            'training_needed' => $trainingNeeded,
            'training_need_probability' => round($overallProbability, 4),
            'analytics_model_version' => (string) ($model['model_version'] ?? 'lna-logistic-unknown'),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recommendation(LearningNeedsAnalysis $entry): array
    {
        $entry->loadMissing('recommendations');
        $recommendations = $entry->recommendations
            ->sortBy('rank')
            ->values();

        if ($recommendations->isEmpty()) {
            if ($entry->training_needed !== true) {
                return [
                    'lna_id' => $entry->id,
                    'focus_area' => $entry->focus_area,
                    'priority_level' => $entry->priority_level,
                    'predictive_skills_gap' => $entry->predictive_skills_gap,
                    'prescriptive_training_recommendation' => null,
                    'training_type' => null,
                    'provider' => null,
                    'track' => $entry->training_needed === false ? 'No immediate training need' : 'Assessment data incomplete',
                    'rationale' => $entry->training_needed === false
                        ? 'The reviewed employee and supervisor ratings did not identify a competency rated below 3.'
                        : 'A recommendation is unavailable because the reviewed assessment does not contain enough ratings.',
                    'training_needed' => $entry->training_needed,
                    'confidence_score' => null,
                    'model_version' => $entry->analytics_model_version,
                    'ranked_recommendations' => [],
                ];
            }

            return [
                ...$this->staticFallback->recommendation($entry),
                'training_needed' => $entry->training_needed,
                'confidence_score' => $entry->training_need_probability,
                'model_version' => $entry->analytics_model_version,
                'ranked_recommendations' => [],
            ];
        }

        $top = $recommendations->first();

        return [
            'lna_id' => $entry->id,
            'focus_area' => $entry->focus_area,
            'priority_level' => $entry->priority_level,
            'predictive_skills_gap' => $entry->predictive_skills_gap,
            'prescriptive_training_recommendation' => $top->training_title,
            'training_type' => $top->training_type,
            'provider' => $top->provider,
            'track' => $top->competency_category ?: 'LNA skill development',
            'rationale' => $top->recommendation_text,
            'training_needed' => (bool) $entry->training_needed,
            'confidence_score' => (float) ($entry->training_need_probability ?? $top->probability),
            'model_version' => $entry->analytics_model_version,
            'ranked_recommendations' => $recommendations->map(fn ($item): array => [
                'rank' => $item->rank,
                'competency_name' => $item->competency_name,
                'competency_category' => $item->competency_category,
                'probability' => (float) $item->probability,
                'priority' => $item->priority,
                'training_title' => $item->training_title,
                'training_type' => $item->training_type,
                'provider' => $item->provider,
                'recommendation_text' => $item->recommendation_text,
                'status' => $item->status,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function model(): ?array
    {
        if ($this->modelLoaded) {
            return $this->model;
        }

        $this->modelLoaded = true;
        $path = (string) config('services.lna.model_path');

        if ($path === '' || ! File::exists($path)) {
            Log::warning('LNA model artifact is missing; reviewed ratings analysis will be used.', ['path' => $path]);

            return null;
        }

        try {
            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $dataSource = is_array($decoded) ? ($decoded['dataset']['source'] ?? null) : null;
            $isSynthetic = is_array($decoded) && ($decoded['dataset']['synthetic'] ?? true) === true;
            $syntheticDemoAllowed = app()->environment(['local', 'testing'])
                || (bool) config('services.lna.demo_mode', false);

            if (! is_array($decoded)
                || ($isSynthetic && (! $syntheticDemoAllowed || $dataSource !== 'synthetic'))
                || (! $isSynthetic && ! in_array($dataSource, ['database', 'validated'], true))) {
                Log::warning('LNA model provenance is not permitted in this environment.', [
                    'path' => $path,
                    'data_source' => $dataSource,
                    'synthetic' => $isSynthetic,
                    'environment' => app()->environment(),
                ]);

                return null;
            }

            $schema = $decoded['feature_schema'] ?? [];
            $featureNames = is_array($schema['feature_names'] ?? null) ? $schema['feature_names'] : [];
            $leakedFeatures = array_intersect(['supervisor_assessment', 'skill_gap'], $featureNames);
            $validationMetrics = $decoded['validation']['metrics'] ?? [];
            $validationRows = (int) ($validationMetrics['rows'] ?? 0);
            $rocAuc = is_numeric($validationMetrics['roc_auc'] ?? null) ? (float) $validationMetrics['roc_auc'] : null;
            $minimumRows = (int) config('services.lna.auto_training.min_validation_rows', 30);
            $minimumRocAuc = (float) config('services.lna.auto_training.min_validation_roc_auc', 0.70);

            if ($featureNames === [] || $leakedFeatures !== [] || $validationRows < $minimumRows || $rocAuc === null || $rocAuc < $minimumRocAuc) {
                Log::warning('LNA model artifact failed runtime validation; rule-based ratings analysis will be used.', [
                    'path' => $path,
                    'validation_rows' => $validationRows,
                    'validation_roc_auc' => $rocAuc,
                    'leaked_features' => array_values($leakedFeatures),
                ]);

                return null;
            }

            $this->model = $decoded;
        } catch (Throwable $exception) {
            Log::warning('LNA model artifact could not be loaded; reviewed ratings analysis will be used.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }

        return $this->model;
    }

    /**
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>  $inputs
     */
    private function predict(array $model, array $inputs): float
    {
        $schema = $model['feature_schema'] ?? [];
        $featureNames = $schema['feature_names'] ?? [];
        $weights = $schema['weights'] ?? [];
        $score = (float) ($schema['intercept'] ?? 0);

        foreach ($featureNames as $index => $featureName) {
            if (in_array($featureName, $schema['numeric_features'] ?? [], true)) {
                $mean = (float) ($schema['numeric_means'][$featureName] ?? 0);
                $scale = (float) ($schema['numeric_scales'][$featureName] ?? 1);
                $value = $this->numeric($inputs[$featureName] ?? null) ?? $mean;
                $featureValue = ($value - $mean) / ($scale ?: 1);
            } else {
                $featureValue = 0.0;
                foreach ($schema['categorical_features'] ?? [] as $field) {
                    $prefix = $field.'=';
                    if (str_starts_with($featureName, $prefix)) {
                        $featureValue = trim((string) ($inputs[$field] ?? '')) === substr($featureName, strlen($prefix)) ? 1.0 : 0.0;
                        break;
                    }
                }
            }

            $score += $featureValue * (float) ($weights[$index] ?? 0);
        }

        return 1 / (1 + exp(-max(-35, min(35, $score))));
    }

    /**
     * @return array{predictive_skills_gap: string, prescriptive_training_recommendation: string|null, training_needed: bool|null, training_need_probability: float|null, analytics_model_version: string, recommendations: array<int, mixed>}
     */
    private function fallbackAnalytics(LearningNeedsAnalysis $entry): array
    {
        $entry->loadMissing('user.employeeRecord');
        $fallback = $this->staticFallback->generate($entry);
        $employeeRatings = collect($entry->skill_assessments ?? []);
        $supervisorRatings = collect($entry->supervisor_skill_assessments ?? []);
        $validRatings = $employeeRatings->values()->concat($supervisorRatings->values())
            ->map(fn (mixed $value): ?float => $this->rating($value))
            ->filter();
        $gaps = $employeeRatings
            ->filter(fn (mixed $value): bool => ($rating = $this->rating($value)) !== null && $rating < 3)
            ->keys()
            ->merge($supervisorRatings
                ->filter(fn (mixed $value): bool => ($rating = $this->rating($value)) !== null && $rating < 3)
                ->keys())
            ->unique()
            ->sort()
            ->values();
        $trainingNeeded = $validRatings->isEmpty() ? null : $gaps->isNotEmpty();
        $gapSummary = match ($trainingNeeded) {
            true => $gaps->implode(', '),
            false => 'No immediate competency gap identified in reviewed ratings.',
            default => 'Training need could not be determined because the reviewed assessment contains no valid competency ratings.',
        };

        return [
            'predictive_skills_gap' => $gapSummary,
            'prescriptive_training_recommendation' => $trainingNeeded === true
                ? $fallback['prescriptive_training_recommendation']
                : null,
            'training_needed' => $trainingNeeded,
            'training_need_probability' => null,
            'analytics_model_version' => 'rule-based-ratings-v1',
            'recommendations' => [],
        ];
    }

    private function rating(mixed $value): ?float
    {
        if ($value === null || $value === 'N/A' || $value === '') {
            return null;
        }

        $rating = $this->numeric($value);

        return $rating !== null && $rating >= 1 && $rating <= 4 ? $rating : null;
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function inferSeniority(?string $position): ?int
    {
        $position = strtolower((string) $position);

        return match (true) {
            str_contains($position, 'director') || str_contains($position, 'chief') => 4,
            str_contains($position, 'supervisor') || str_contains($position, 'manager') => 3,
            str_contains($position, 'senior') => 3,
            str_contains($position, 'aide') || str_contains($position, 'assistant') => 1,
            $position !== '' => 2,
            default => null,
        };
    }

    private function inferRoleFamily(?string $position): string
    {
        $position = strtolower((string) $position);

        return match (true) {
            str_contains($position, 'ict') || str_contains($position, 'information technology') || str_contains($position, 'programmer') => 'ict',
            str_contains($position, 'account') => 'accounting',
            str_contains($position, 'procurement') || str_contains($position, 'bids') => 'procurement',
            str_contains($position, 'legal') || str_contains($position, 'attorney') => 'legal',
            str_contains($position, 'nurse') || str_contains($position, 'medical') => 'health',
            default => '',
        };
    }

    /**
     * @return array{training_type: string, provider: string|null}
     */
    private function trainingMetadata(string $trainingTitle): array
    {
        $lower = strtolower($trainingTitle);

        return [
            'training_type' => str_contains($lower, 'project') || str_contains($lower, 'digital') ? 'Invitational' : 'In-house',
            'provider' => null,
        ];
    }
}
