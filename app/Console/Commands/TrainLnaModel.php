<?php

namespace App\Console\Commands;

use App\Models\LearningNeedsAnalysis;
use App\Models\LnaModelTrainingRun;
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
        'employee_assessment',
        'supervisor_assessment',
        'required_level',
        'skill_gap',
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
            ->with(['user.employeeRecord'])
            ->withCount([
                'trainingApplications as trainings_last_3_years_count' => fn ($query) => $query
                    ->where('created_at', '>=', now()->subYears(3)),
            ])
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

            foreach ($supervisorRatings as $skill => $supervisorRating) {
                $employeeScore = $this->rating($employeeRatings[$skill] ?? null);
                $supervisorScore = $this->rating($supervisorRating);

                if ($employeeScore === null || $supervisorScore === null) {
                    continue;
                }

                $metadata = $catalog[strtolower(trim((string) $skill))] ?? [];
                $requiredLevel = (float) ($metadata['required_level'] ?? 3);
                $skillGap = max(0, $requiredLevel - $supervisorScore);

                $rows[] = [
                    'employee_assessment' => $employeeScore,
                    'supervisor_assessment' => $supervisorScore,
                    'required_level' => $requiredLevel,
                    'skill_gap' => $skillGap,
                    'ipcr_rating' => $this->numeric($entry->ipcr_rating),
                    'trainings_last_3_years' => (int) ($entry->trainings_last_3_years_count ?? 0),
                    'years_of_service' => null,
                    'seniority_level' => $this->inferSeniority($position),
                    'role_family' => $this->inferRoleFamily($position),
                    'education_level' => '',
                    'employment_status' => (string) $employmentStatus,
                    'competency_category' => (string) ($metadata['competency_category'] ?? 'Database-derived competency'),
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

            return is_array($artifact['recommendation_catalog'] ?? null) ? $artifact['recommendation_catalog'] : [];
        } catch (Throwable) {
            return [];
        }
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
