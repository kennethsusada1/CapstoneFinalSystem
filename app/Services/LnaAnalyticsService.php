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
     * @return array{predictive_skills_gap: string, prescriptive_training_recommendation: string|null, training_needed: bool, training_need_probability: float|null, analytics_model_version: string, recommendations: array<int, array<string, mixed>>}
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
        $trainingCount = TrainingApplication::query()
            ->where('user_id', $entry->user_id)
            ->where('created_at', '>=', now()->subYears(3))
            ->count();
        $recommendationCatalog = $model['recommendation_catalog'] ?? [];
        $threshold = (float) ($model['feature_schema']['threshold'] ?? 0.5);
        $contextFamily = $this->inferContextFamily($entry);

        $predictions = $employeeRatings
            ->map(function (mixed $employeeRating, string $skill) use ($entry, $model, $supervisorRatings, $trainingCount, $recommendationCatalog, $threshold, $fallback, $employeeRecord, $contextFamily): ?array {
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
                    'supervisor_assessment' => $supervisorScore,
                    'required_level' => $requiredLevel,
                    'skill_gap' => $skillGap,
                    'ipcr_rating' => $this->numeric($entry->ipcr_rating),
                    'trainings_last_3_years' => $trainingCount,
                    'years_of_service' => null,
                    'seniority_level' => $this->inferSeniority($employeeRecord?->position),
                    'role_family' => $this->inferRoleFamily($employeeRecord?->position),
                    'education_level' => '',
                    'employment_status' => (string) ($employeeRecord->employment_status ?? ''),
                    'competency_category' => (string) ($catalog['competency_category'] ?? ''),
                ]);
                $trainingTitle = (string) ($catalog['training_title'] ?? $fallback['prescriptive_training_recommendation']);
                $category = (string) ($catalog['competency_category'] ?? 'AI-ranked skill development');
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
                    'context_match' => $contextFamily === null || $this->matchesContext(
                        $contextFamily,
                        $skill,
                        $category,
                        $trainingTitle,
                    ),
                ];
            })
            ->filter()
            ->sortByDesc('probability')
            ->values();

        // The logistic model estimates the likelihood of a training need for
        // every assessed skill. The LNA's focus area is the business context
        // that determines which of those predictions should be surfaced to
        // the supervisor. Without this filter, an unrelated high-probability
        // skill can displace the requested communication recommendation.
        if ($contextFamily !== null) {
            $predictions = $predictions
                ->filter(fn (array $prediction): bool => $prediction['context_match'])
                ->values();
        }

        $overallProbability = (float) ($predictions->max('probability') ?? 0);
        $recommendations = $predictions
            ->filter(fn (array $prediction): bool => $prediction['probability'] >= $threshold)
            ->take(5)
            ->values()
            ->map(function (array $prediction, int $index): array {
                unset($prediction['skill_gap']);
                unset($prediction['context_match']);
                $prediction['rank'] = $index + 1;

                return $prediction;
            })
            ->all();
        $trainingNeeded = $overallProbability >= $threshold;
        $topRecommendation = $recommendations[0] ?? null;

        // Current production employee records may not yet contain every
        // profile feature used by the trained model. Preserve a transparent
        // rule-based recommendation in that case instead of displaying an
        // empty result as if the model had enough evidence.
        if ($recommendations === []) {
            return [
                ...$this->fallbackAnalytics($entry),
                'analytics_model_version' => (string) ($model['model_version'] ?? 'lna-logistic-unknown').'-fallback',
            ];
        }

        return [
            'predictive_skills_gap' => collect($recommendations)->pluck('competency_name')->implode(', '),
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
            'track' => $top->competency_category ?: 'AI-ranked skill development',
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
            Log::warning('LNA model artifact is missing; static fallback will be used.', ['path' => $path]);

            return null;
        }

        try {
            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $this->model = is_array($decoded) ? $decoded : null;
        } catch (Throwable $exception) {
            Log::warning('LNA model artifact could not be loaded; static fallback will be used.', [
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
     * @return array{predictive_skills_gap: string, prescriptive_training_recommendation: string|null, training_needed: bool, training_need_probability: float|null, analytics_model_version: string, recommendations: array<int, mixed>}
     */
    private function fallbackAnalytics(LearningNeedsAnalysis $entry): array
    {
        $fallback = $this->staticFallback->generate($entry);

        return [
            ...$fallback,
            'training_needed' => true,
            'training_need_probability' => null,
            'analytics_model_version' => 'static-fallback-v1',
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

    private function inferContextFamily(LearningNeedsAnalysis $entry): ?string
    {
        $context = strtolower(trim(implode(' ', array_filter([
            $entry->focus_area,
            $entry->competency_gap,
            $entry->proposed_intervention,
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== ''))));

        if ($context === '') {
            return null;
        }

        $families = [
            'communication' => [
                'communicat', 'writing', 'report', 'present', 'public speak',
                'listen', 'correspond', 'negotiat', 'stakeholder', 'facilitat',
                'client', 'customer', 'media',
            ],
            'leadership' => [
                'leadership', 'leader', 'supervis', 'team management', 'delegat',
                'coaching', 'coach', 'motivat', 'conflict resolution',
            ],
            'digital_data' => [
                'digital', 'data', 'excel', 'computer', 'system', 'database',
                'information technology', 'ict',
            ],
            'planning' => [
                'project', 'planning', 'plan', 'monitor', 'implementation',
                'scheduling',
            ],
            'ethics_compliance' => [
                'integrity', 'professionalism', 'ethic', 'compliance', 'risk',
                'code of conduct',
            ],
            'adaptability' => [
                'adapt', 'flexib', 'resilien', 'stress management', 'innovation',
                'creative',
            ],
            'organization' => [
                'time management', 'multitask', 'resource management',
                'administrative', 'organization',
            ],
            'problem_solving' => [
                'problem solving', 'analytical thinking', 'critical thinking',
                'troubleshoot',
            ],
        ];

        foreach ($families as $family => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($context, $keyword)) {
                    return $family;
                }
            }
        }

        return null;
    }

    private function matchesContext(string $family, string $skill, string $category, string $trainingTitle): bool
    {
        $candidate = strtolower(trim($skill.' '.$category.' '.$trainingTitle));

        $keywords = match ($family) {
            'communication' => [
                'communication', 'writing', 'report', 'present', 'speaking',
                'listen', 'correspond', 'negotiat', 'stakeholder', 'facilitat',
                'client', 'customer', 'media',
            ],
            'leadership' => [
                'leadership', 'leader', 'supervis', 'team management', 'delegat',
                'coach', 'motivat', 'conflict',
            ],
            'digital_data' => [
                'digital', 'data', 'excel', 'computer', 'system', 'database',
                'information technology', 'ict',
            ],
            'planning' => [
                'project', 'planning', 'plan', 'monitor', 'implementation',
                'scheduling',
            ],
            'ethics_compliance' => [
                'integrity', 'professional', 'ethic', 'compliance', 'risk',
                'code of conduct',
            ],
            'adaptability' => [
                'adapt', 'flexib', 'resilien', 'stress', 'innovation', 'creative',
            ],
            'organization' => [
                'time management', 'multitask', 'resource management',
                'administrative', 'organization',
            ],
            'problem_solving' => [
                'problem solving', 'analytical', 'critical thinking',
                'troubleshoot',
            ],
            default => [],
        };

        foreach ($keywords as $keyword) {
            if (str_contains($candidate, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{training_type: string, provider: string}
     */
    private function trainingMetadata(string $trainingTitle): array
    {
        $lower = strtolower($trainingTitle);

        return [
            'training_type' => str_contains($lower, 'project') || str_contains($lower, 'digital') ? 'Invitational' : 'In-house',
            'provider' => 'HRDC Learning and Development Unit',
        ];
    }
}
