<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use App\Services\LnaAnalyticsService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeDashboardController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    protected function recommendationProfile(LearningNeedsAnalysis $entry): array
    {
        return app(LnaAnalyticsService::class)->recommendation($entry);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $recommendations
     * @param  Collection<int, TrainingApplication>  $trainings
     * @param  Collection<int, LearningActionPlan>  $lapEntries
     * @return Collection<int, array<string, string>>
     */
    protected function notifications(Collection $recommendations, Collection $trainings, Collection $lapEntries): Collection
    {
        $items = collect();
        $availableRecommendationCount = 0;

        foreach ($recommendations as $recommendation) {
            $matchedTraining = $trainings->first(fn (TrainingApplication $training) => $training->training_title === $recommendation['prescriptive_training_recommendation']);

            if (! $matchedTraining) {
                $availableRecommendationCount++;

                continue;
            }

            if ($matchedTraining->status === 'applied') {
                $items->push([
                    'title' => 'Application Recorded',
                    'message' => "{$matchedTraining->training_title} is already in your submitted training applications.",
                ]);
            }
        }

        if ($availableRecommendationCount > 0) {
            $assessmentLabel = $availableRecommendationCount === 1 ? 'assessment has' : 'assessments have';

            $items->push([
                'title' => 'Training Recommendations Available',
                'message' => "{$availableRecommendationCount} supervisor-reviewed LNA {$assessmentLabel} training recommendations ready. Review your options and choose the training that best fits your development needs.",
            ]);
        }

        $trainingsNeedingLap = $trainings->filter(fn (TrainingApplication $training): bool => ($training->status === 'completed' || $training->is_attended)
            && ! $lapEntries->contains(fn (LearningActionPlan $lap): bool => $lap->training_title === $training->training_title));

        if ($trainingsNeedingLap->isNotEmpty()) {
            $trainingCount = $trainingsNeedingLap->count();
            $trainingLabel = $trainingCount === 1 ? 'completed training needs' : 'completed trainings need';

            $items->push([
                'title' => 'Learning Action Plan Required',
                'message' => "{$trainingCount} {$trainingLabel} a Learning Action Plan. Submit one for each training you completed.",
            ]);
        }

        return $items->unique('message')->values();
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $startDate = now()->subMonths(5)->startOfMonth();
        $period = CarbonPeriod::create($startDate, '1 month', now()->startOfMonth());
        $lnaEntries = LearningNeedsAnalysis::query()
            ->where('user_id', $user->id)
            ->with('recommendations')
            ->latest()
            ->get();

        $trainings = TrainingApplication::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $lapEntries = LearningActionPlan::query()->where('user_id', $user->id)->latest()->get();
        $recommendations = $lnaEntries
            ->filter(fn (LearningNeedsAnalysis $entry): bool => $entry->status === 'reviewed'
                && $entry->analytics_generated_at !== null
                && $entry->predictive_skills_gap !== null
                && $entry->prescriptive_training_recommendation !== null)
            ->map(fn (LearningNeedsAnalysis $item) => $this->recommendationProfile($item))
            ->unique(fn (array $item) => $item['focus_area'].'|'.$item['prescriptive_training_recommendation'])
            ->values();

        $completedByMonth = $trainings
            ->filter(fn (TrainingApplication $item) => $item->completed_on !== null)
            ->groupBy(fn (TrainingApplication $item) => $item->completed_on?->format('Y-m') ?? 'unknown');

        return Inertia::render('Employee/Dashboard', [
            'stats' => [
                'lnaSubmitted' => $lnaEntries->count(),
                'skillsGapCount' => $recommendations->count(),
                'recommendedTrainings' => $recommendations->count(),
                'trainingApplied' => $trainings->count(),
                'trainingAttended' => $trainings->where('is_attended', true)->count(),
                'lapCompleted' => $lapEntries->where('status', 'completed')->count(),
            ],
            'progressCards' => [
                [
                    'label' => 'Predictive Skills Gaps',
                    'value' => $recommendations->count(),
                    'suffix' => '',
                ],
                [
                    'label' => 'Training Applications Submitted',
                    'value' => $trainings->where('status', 'applied')->count(),
                    'suffix' => '',
                ],
                [
                    'label' => 'LAP Still Required',
                    'value' => $trainings->filter(fn (TrainingApplication $training) => ($training->status === 'completed' || $training->is_attended) && ! $lapEntries->contains(fn (LearningActionPlan $lap) => $lap->training_title === $training->training_title))->count(),
                    'suffix' => '',
                ],
            ],
            'charts' => [
                'monthlyCompletion' => collect(iterator_to_array($period, false))->map(fn ($date) => [
                    'label' => $date->format('M'),
                    'completed' => $completedByMonth->get($date->format('Y-m'))?->count() ?? 0,
                ])->values(),
                'trainingStatus' => [
                    ['label' => 'Applied', 'value' => $trainings->where('status', 'applied')->count(), 'color' => '#60a5fa'],
                    ['label' => 'Ongoing', 'value' => $trainings->where('status', 'ongoing')->count(), 'color' => '#f59e0b'],
                    ['label' => 'Completed', 'value' => $trainings->where('status', 'completed')->count(), 'color' => '#34d399'],
                ],
            ],
            'recommendations' => $recommendations,
            'recentTrainings' => $trainings->take(6)->map(fn (TrainingApplication $item) => [
                'id' => $item->id,
                'training_title' => $item->training_title,
                'training_type' => $item->training_type,
                'progress_percent' => $item->progress_percent,
                'status' => $item->status,
                'is_attended' => $item->is_attended,
                'completed_on' => $item->completed_on?->toDateString(),
            ])->values(),
            'notifications' => $this->notifications($recommendations, $trainings, $lapEntries),
            'highlights' => [
                'Submit your Learning Needs Analysis assessment for supervisor evaluation.',
                'Apply for training after your supervisor endorses the recommended intervention.',
                'Track Secretariat processing and the final HRDC program decision.',
            ],
        ]);
    }
}
