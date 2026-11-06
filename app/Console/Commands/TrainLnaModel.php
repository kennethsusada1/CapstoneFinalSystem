<?php

namespace App\Console\Commands;

use App\Models\LearningNeedsAnalysis;
use App\Models\LnaModelTrainingRun;
use App\Models\TrainingApplication;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

class TrainLnaModel extends Command
{
    protected $signature = 'lna:train {--force : Train again even when the database data has not changed}';

    protected $description = 'Train and promote the LNA model when enough reviewed database data is available';

    /**
     * @var list<string>
     */
    private const CSV_COLUMNS = [
        'competency_name',
        'recommended_training',
        'employee_assessment',
        'required_level',
        'ipcr_rating',
        'trainings_last_3_years',
        'years_of_service',
        'seniority_level',
        'role_family',
        'education_level',
        'employment_status',
        'competency_category',
        'year',
        'training_needed',
    ];

    public function handle(): int
    {
        if (! (bool) config('services.lna.auto_training.enabled', true)) {
            $this->line('Automatic LNA training is disabled by configuration.');

            return self::SUCCESS;
        }

        $entries = $this->eligibleEntries();
        $rows = $this->trainingRows($entries);
        $counts = $this->rowCounts($rows);
        $signature = $this->dataSignature($rows);
        $trigger = $this->option('force') ? 'manual-force' : 'scheduled';

        if (! $this->option('force') && LnaModelTrainingRun::query()
            ->where('status', 'succeeded')
            ->where('data_signature', $signature)
            ->exists()) {
            $this->line('No new or changed reviewed LNA data since the last successful model run.');

            return self::SUCCESS;
        }

        $minimumIssue = $this->minimumDataIssue($rows, $counts);

        if ($minimumIssue !== null) {
            $this->recordSkipped($trigger, $counts, $signature, $minimumIssue);
            $this->warn($minimumIssue);

            return self::SUCCESS;
        }

        $run = LnaModelTrainingRun::query()->create([
            'status' => 'running',
            'trigger' => $trigger,
            'source_rows' => $counts['total'],
            'positive_rows' => $counts['positive'],
            'negative_rows' => $counts['negative'],
            'data_signature' => $signature,
            'started_at' => now(),
        ]);

        $directory = storage_path('app/private/lna-training');
        File::ensureDirectoryExists($directory);
        $inputPath = $directory.DIRECTORY_SEPARATOR.'run-'.$run->id.'.csv';
        $outputPath = $directory.DIRECTORY_SEPARATOR.'run-'.$run->id.'.json';
        $modelVersion = 'lna-logistic-v'.$run->id;

        try {
            $this->writeCsv($inputPath, $rows);
            $result = Process::path(base_path())
                ->timeout((int) config('services.lna.auto_training.timeout', 600))
                ->run([
                    (string) config('services.lna.auto_training.python_binary', 'python'),
                    base_path((string) config('services.lna.auto_training.trainer_script', 'scripts/train_lna_model.py')),
                    '--input',
                    $inputPath,
                    '--output',
                    $outputPath,
                    '--threshold',
                    (string) config('services.lna.threshold', 0.5),
                    '--model-version',
                    $modelVersion,
                    '--data-source',
                    'database',
                ]);

            if (! $result->successful()) {
                throw new \RuntimeException(trim($result->errorOutput()) ?: 'The Python trainer returned a failure status.');
            }

            if (! File::exists($outputPath)) {
                throw new \RuntimeException('The Python trainer did not produce a model artifact.');
            }

            $artifact = json_decode(File::get($outputPath), true, 512, JSON_THROW_ON_ERROR);
            $metrics = $artifact['validation']['metrics'] ?? [];
            $this->assertModelQuality($artifact, $metrics);

            $activePath = (string) config('services.lna.model_path');
            File::ensureDirectoryExists(dirname($activePath));
            File::replace($activePath, File::get($outputPath));

            $run->update([
                'model_version' => (string) ($artifact['model_version'] ?? $modelVersion),
                'status' => 'succeeded',
                'validation_rows' => (int) ($metrics['rows'] ?? 0),
                'validation_roc_auc' => (float) ($metrics['roc_auc'] ?? 0),
                'validation_metrics' => $metrics,
                'artifact_path' => $activePath,
                'message' => 'Model validated and promoted to the active artifact.',
                'completed_at' => now(),
            ]);

            $this->info(sprintf(
                'LNA model %s trained on %d rows and promoted (validation ROC-AUC: %.4f).',
                $run->model_version,
                $counts['total'],
                (float) ($metrics['roc_auc'] ?? 0),
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'message' => mb_substr($exception->getMessage(), 0, 5000),
                'completed_at' => now(),
            ]);
            $this->error('LNA model training failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::delete([$inputPath, $outputPath]);
        }
    }

    /**
     * @return Collection<int, LearningNeedsAnalysis>
     */
    private function eligibleEntries(): Collection
    {
        return LearningNeedsAnalysis::query()
            ->where('status', 'reviewed')
            ->whereNotNull('skill_assessments')
            ->whereNotNull('supervisor_skill_assessments')
            ->whereHas('user.employeeRecord', function ($query): void {
                $query
                    ->whereRaw('LOWER(TRIM(employment_status)) = ?', ['permanent'])
                    ->whereNotNull('office')
                    ->whereNotNull('position')
                    ->whereNotNull('education_level')
                    ->whereNotNull('role_family')
                    ->whereNotNull('seniority_level')
                    ->whereNotNull('hire_date');
            })
            ->where(function ($query): void {
                $query
                    ->whereNotNull('ipcr_rating')
                    ->orWhereHas('user.employeeRecord.performanceRecords', fn ($performance) => $performance->whereNotNull('ipcr_rating'));
            })
            ->with(['user.employeeRecord.performanceRecords'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, LearningNeedsAnalysis>  $entries
     * @return list<array<string, int|float|string|null>>
     */
    private function trainingRows(Collection $entries): array
    {
        $catalog = $this->recommendationCatalog();
        $rows = [];

        foreach ($entries as $entry) {
            $employeeRatings = is_array($entry->skill_assessments) ? $entry->skill_assessments : [];
            $supervisorRatings = is_array($entry->supervisor_skill_assessments) ? $entry->supervisor_skill_assessments : [];
            $employeeRecord = $entry->user->employeeRecord;
            $position = $employeeRecord?->position;
            $employmentStatus = $employeeRecord?->employment_status;
            $year = $entry->submitted_on
                ? $entry->submitted_on->year
                : ($entry->created_at ? $entry->created_at->year : now()->year);
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
            $serviceYears = $employeeRecord?->hire_date === null
                ? null
                : max(0, $employeeRecord->hire_date->diffInYears($asOf));
            $performanceRatings = $employeeRecord?->performanceRecords
                ?->whereBetween('review_year', [$year - 3, $year])
                ->pluck('ipcr_rating')
                ->filter(fn ($rating): bool => is_numeric($rating)) ?? collect();
            $ipcrAverage = $performanceRatings->isNotEmpty()
                ? round((float) $performanceRatings->avg(), 2)
                : $this->numeric($entry->ipcr_rating);

            if ($serviceYears === null || $ipcrAverage === null) {
                continue;
            }

            foreach ($supervisorRatings as $skill => $supervisorRating) {
                $employeeScore = $this->rating($employeeRatings[$skill] ?? null);
                $supervisorScore = $this->rating($supervisorRating);

                if ($employeeScore === null || $supervisorScore === null) {
                    continue;
                }

                $metadata = array_merge(
                    $this->defaultRecommendation((string) $skill),
                    $catalog[strtolower(trim((string) $skill))] ?? [],
                );
                $requiredLevel = (float) ($metadata['required_level'] ?? 3);
                $rows[] = [
                    'competency_name' => (string) $skill,
                    'recommended_training' => (string) ($metadata['training_title'] ?? 'Functional Competency Enhancement Training'),
                    'employee_assessment' => $employeeScore,
                    'required_level' => $requiredLevel,
                    'ipcr_rating' => $ipcrAverage,
                    'trainings_last_3_years' => $trainingCount,
                    'years_of_service' => $serviceYears,
                    'seniority_level' => $employeeRecord->seniority_level ?? $this->inferSeniority($position),
                    'role_family' => $employeeRecord?->role_family ?: $this->inferRoleFamily($position),
                    'education_level' => (string) ($employeeRecord->education_level ?? ''),
                    'employment_status' => (string) $employmentStatus,
                    'competency_category' => (string) ($metadata['competency_category'] ?? 'Core Functional Capability'),
                    'year' => $year,
                    // Supervisor ratings are the initial human-reviewed label:
                    // 1-2 means training needed, 3-4 means no immediate need.
                    'training_needed' => $supervisorScore <= 2 ? 1 : 0,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, int|float|string|null>>  $rows
     * @return array{total: int, positive: int, negative: int, years: list<int>}
     */
    private function rowCounts(array $rows): array
    {
        $positive = count(array_filter($rows, fn (array $row): bool => (int) $row['training_needed'] === 1));
        $years = array_values(array_unique(array_map(fn (array $row): int => (int) $row['year'], $rows)));
        sort($years);

        return [
            'total' => count($rows),
            'positive' => $positive,
            'negative' => count($rows) - $positive,
            'years' => $years,
        ];
    }

    /**
     * @param  list<array<string, int|float|string|null>>  $rows
     */
    private function dataSignature(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, int|float|string|null>>  $rows
     * @param  array{total: int, positive: int, negative: int, years: list<int>}  $counts
     */
    private function minimumDataIssue(array $rows, array $counts): ?string
    {
        $minRows = (int) config('services.lna.auto_training.min_rows', 100);
        $minPositive = (int) config('services.lna.auto_training.min_positive_rows', 20);
        $minNegative = (int) config('services.lna.auto_training.min_negative_rows', 20);
        $minYears = (int) config('services.lna.auto_training.min_years', 2);

        if ($counts['total'] < $minRows) {
            return "Automatic training is waiting for at least {$minRows} competency rows; {$counts['total']} are currently available.";
        }

        if ($counts['positive'] < $minPositive || $counts['negative'] < $minNegative) {
            return "Automatic training is waiting for both classes: at least {$minPositive} positive and {$minNegative} negative rows are required (found {$counts['positive']} / {$counts['negative']}).";
        }

        if (count($counts['years']) < $minYears) {
            return "Automatic training is waiting for data from at least {$minYears} different years.";
        }

        $minValidationRows = (int) config('services.lna.auto_training.min_validation_rows', 30);
        $latestYear = end($counts['years']);
        $validationRows = count(array_filter($rows, fn (array $row): bool => (int) $row['year'] === $latestYear));

        if ($validationRows < $minValidationRows) {
            return "Automatic training is waiting for at least {$minValidationRows} rows in the latest validation year; {$validationRows} are available.";
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $artifact
     * @param  array<string, mixed>  $metrics
     */
    private function assertModelQuality(array $artifact, array $metrics): void
    {
        $validationRows = (int) ($metrics['rows'] ?? 0);
        $rocAuc = is_numeric($metrics['roc_auc'] ?? null) ? (float) $metrics['roc_auc'] : null;
        $minimumRows = (int) config('services.lna.auto_training.min_validation_rows', 30);
        $minimumRocAuc = (float) config('services.lna.auto_training.min_validation_roc_auc', 0.70);

        if (! isset($artifact['feature_schema'], $artifact['model_version'])) {
            throw new \RuntimeException('The generated artifact is missing its model schema or version.');
        }

        if (($artifact['dataset']['synthetic'] ?? true) !== false
            || ! in_array($artifact['dataset']['source'] ?? null, ['database', 'validated'], true)) {
            throw new \RuntimeException('Synthetic training data cannot be promoted to the active LNA model.');
        }

        $featureNames = $artifact['feature_schema']['feature_names'] ?? [];
        if (array_intersect(['supervisor_assessment', 'skill_gap'], $featureNames) !== []) {
            throw new \RuntimeException('The model contains supervisor-derived target leakage features.');
        }

        if ($validationRows < $minimumRows || $rocAuc === null || $rocAuc < $minimumRocAuc) {
            throw new \RuntimeException(sprintf(
                'Validation quality did not meet the promotion gate (rows: %d, ROC-AUC: %s, required: %d rows and %.2f ROC-AUC).',
                $validationRows,
                $rocAuc === null ? 'n/a' : number_format($rocAuc, 4),
                $minimumRows,
                $minimumRocAuc,
            ));
        }
    }

    /**
     * @param  list<array<string, int|float|string|null>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create the temporary LNA training dataset.');
        }

        try {
            fputcsv($handle, self::CSV_COLUMNS);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn (string $column): int|float|string|null => $row[$column] ?? null, self::CSV_COLUMNS));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array{total: int, positive: int, negative: int, years: list<int>}  $counts
     */
    private function recordSkipped(string $trigger, array $counts, string $signature, string $message): void
    {
        LnaModelTrainingRun::query()->create([
            'status' => 'skipped',
            'trigger' => $trigger,
            'source_rows' => $counts['total'],
            'positive_rows' => $counts['positive'],
            'negative_rows' => $counts['negative'],
            'data_signature' => $signature,
            'message' => $message,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function recommendationCatalog(): array
    {
        $path = (string) config('services.lna.model_path');

        if ($path === '' || ! File::exists($path)) {
            return [];
        }

        try {
            $artifact = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            $source = $artifact['dataset']['source'] ?? null;
            $metrics = $artifact['validation']['metrics'] ?? [];
            $validationRows = (int) ($metrics['rows'] ?? 0);
            $rocAuc = is_numeric($metrics['roc_auc'] ?? null) ? (float) $metrics['roc_auc'] : null;
            $minimumRows = (int) config('services.lna.auto_training.min_validation_rows', 30);
            $minimumRocAuc = (float) config('services.lna.auto_training.min_validation_roc_auc', 0.70);

            if (($artifact['dataset']['synthetic'] ?? true) !== false
                || ! in_array($source, ['database', 'validated'], true)
                || $validationRows < $minimumRows
                || $rocAuc === null
                || $rocAuc < $minimumRocAuc) {
                return [];
            }

            return is_array($artifact['recommendation_catalog'] ?? null) ? $artifact['recommendation_catalog'] : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{competency_category: string, training_title: string}
     */
    private function defaultRecommendation(string $competency): array
    {
        $competency = strtolower(trim($competency));

        return match (true) {
            str_contains($competency, 'lead') || str_contains($competency, 'supervis') || str_contains($competency, 'team management') => [
                'competency_category' => 'Leadership and Supervision',
                'training_title' => 'Supervisory Development Program',
            ],
            str_contains($competency, 'data') || str_contains($competency, 'excel') || str_contains($competency, 'digital') || str_contains($competency, 'system') => [
                'competency_category' => 'Digital Productivity and Data Management',
                'training_title' => 'Digital Productivity and Data Management Workshop',
            ],
            str_contains($competency, 'commun') || str_contains($competency, 'writing') || str_contains($competency, 'report') || str_contains($competency, 'present') => [
                'competency_category' => 'Communication and Technical Writing',
                'training_title' => 'Technical Writing and Presentation Skills Training',
            ],
            str_contains($competency, 'customer') || str_contains($competency, 'client') || str_contains($competency, 'service') => [
                'competency_category' => 'Customer Service Excellence',
                'training_title' => 'Customer Service Excellence Program',
            ],
            str_contains($competency, 'plan') || str_contains($competency, 'project') || str_contains($competency, 'monitor') => [
                'competency_category' => 'Planning and Project Monitoring',
                'training_title' => 'Project Planning and Monitoring Workshop',
            ],
            default => [
                'competency_category' => 'Core Functional Capability',
                'training_title' => 'Functional Competency Enhancement Training',
            ],
        };
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
}
