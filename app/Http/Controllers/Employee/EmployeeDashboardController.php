<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
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
        $context = strtolower(trim($entry->focus_area.' '.$entry->competency_gap.' '.$entry->proposed_intervention));

        $profile = match (true) {
            str_contains($context, 'lead') || str_contains($context, 'supervis') => [
                'skill_gap' => 'Leadership, coaching, and team supervision capability',
                'training_title' => 'Supervisory Development Program',
            ],
            str_contains($context, 'data') || str_contains($context, 'excel') || str_contains($context, 'digital') || str_contains($context, 'system') => [
                'skill_gap' => 'Digital tools, records management, and data analysis capability',
                'training_title' => 'Digital Productivity and Data Management Workshop',
            ],
            str_contains($context, 'commun') || str_contains($context, 'writing') || str_contains($context, 'report') || str_contains($context, 'present') => [
                'skill_gap' => 'Written communication, presentation, and report preparation capability',
                'training_title' => 'Technical Writing and Presentation Skills Training',
            ],
            str_contains($context, 'customer') || str_contains($context, 'client') || str_contains($context, 'service') => [
                'skill_gap' => 'Client handling, service delivery, and stakeholder engagement capability',
                'training_title' => 'Customer Service Excellence Program',
            ],
            str_contains($context, 'plan') || str_contains($context, 'project') || str_contains($context, 'monitor') => [
                'skill_gap' => 'Planning, implementation monitoring, and target management capability',
                'training_title' => 'Project Planning and Monitoring Workshop',
            ],
            default => [
                'skill_gap' => 'Role-specific functional competency requiring further development',
                'training_title' => 'Functional Competency Enhancement Training',
            ],
        };

        return [
            'focus_area' => $entry->focus_area,
            'priority_level' => $entry->priority_level,
            'prescribed_skills_gap' => $profile['skill_gap'],
            'predicted_training_recommendation' => $profile['training_title'],
        ];
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

        foreach ($recommendations as $recommendation) {
            $matchedTraining = $trainings->first(fn (TrainingApplication $training) => $training->training_title === $recommendation['predicted_training_recommendation']);

            if (! $matchedTraining) {
                $items->push([
                    'title' => 'Recommended Training',
                    'message' => "Undergo {$recommendation['predicted_training_recommendation']} for {$recommendation['focus_area']}.",
                ]);

                continue;
            }

            if ($matchedTraining->status === 'applied') {
                $items->push([
                    'title' => 'Application Recorded',
                    'message' => "{$matchedTraining->training_title} is already in your submitted training applications.",
                ]);
            }
        }

        foreach ($trainings->filter(fn (TrainingApplication $training) => $training->status === 'completed' || $training->is_attended) as $training) {
            $hasLap = $lapEntries->contains(fn (LearningActionPlan $lap) => $lap->training_title === $training->training_title);

            if (! $hasLap) {
                $items->push([
                    'title' => 'Learning Action Plan',
                    'message' => "Submit your Learning Action Plan for {$training->training_title}.",
                ]);
            }
        }

        return $items->unique('message')->values();
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $startDate = now()->subMonths(5)->startOfMonth();
        $period = CarbonPeriod::create($startDate, '1 month', now()->startOfMonth());
        $lnaEntries = LearningNeedsAnalysis::query()->where('user_id', $user->id)->latest()->get();

        $trainings = TrainingApplication::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $lapEntries = LearningActionPlan::query()->where('user_id', $user->id)->latest()->get();
        $recommendations = $lnaEntries
            ->where('status', 'reviewed')
            ->map(fn (LearningNeedsAnalysis $item) => $this->recommendationProfile($item))
            ->unique(fn (array $item) => $item['focus_area'].'|'.$item['predicted_training_recommendation'])
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
                    'label' => 'Prescribed Skills Gaps',
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
