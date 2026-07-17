<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLearningController extends Controller
{
    protected function recommendationProfile(LearningNeedsAnalysis $entry): array
    {
        $context = strtolower(trim($entry->focus_area.' '.$entry->competency_gap.' '.$entry->proposed_intervention));

        $profile = match (true) {
            str_contains($context, 'lead') || str_contains($context, 'supervis') => [
                'track' => 'Leadership and Supervision',
                'skill_gap' => 'Leadership, coaching, and team supervision capability',
                'training_title' => 'Supervisory Development Program',
                'training_type' => 'In-house',
                'provider' => 'HRDC Learning and Development Unit',
            ],
            str_contains($context, 'data') || str_contains($context, 'excel') || str_contains($context, 'digital') || str_contains($context, 'system') => [
                'track' => 'Digital Productivity and Data Management',
                'skill_gap' => 'Digital tools, records management, and data analysis capability',
                'training_title' => 'Digital Productivity and Data Management Workshop',
                'training_type' => 'Invitational',
                'provider' => 'Civil Service Commission / External ICT Partner',
            ],
            str_contains($context, 'commun') || str_contains($context, 'writing') || str_contains($context, 'report') || str_contains($context, 'present') => [
                'track' => 'Communication and Technical Writing',
                'skill_gap' => 'Written communication, presentation, and report preparation capability',
                'training_title' => 'Technical Writing and Presentation Skills Training',
                'training_type' => 'In-house',
                'provider' => 'Secretariat and HRDC',
            ],
            str_contains($context, 'customer') || str_contains($context, 'client') || str_contains($context, 'service') => [
                'track' => 'Customer Service Excellence',
                'skill_gap' => 'Client handling, service delivery, and stakeholder engagement capability',
                'training_title' => 'Customer Service Excellence Program',
                'training_type' => 'In-house',
                'provider' => 'HRDC Service Quality Team',
            ],
            str_contains($context, 'plan') || str_contains($context, 'project') || str_contains($context, 'monitor') => [
                'track' => 'Planning and Project Monitoring',
                'skill_gap' => 'Planning, implementation monitoring, and target management capability',
                'training_title' => 'Project Planning and Monitoring Workshop',
                'training_type' => 'Invitational',
                'provider' => 'DILG / Accredited Training Provider',
            ],
            default => [
                'track' => 'Core Functional Capability',
                'skill_gap' => 'Role-specific functional competency requiring further development',
                'training_title' => 'Functional Competency Enhancement Training',
                'training_type' => 'In-house',
                'provider' => 'HRDC Learning and Development Unit',
            ],
        };

        $priorityLabel = ucfirst((string) $entry->priority_level);

        return [
            'lna_id' => $entry->id,
            'focus_area' => $entry->focus_area,
            'priority_level' => $entry->priority_level,
            'prescribed_skills_gap' => $profile['skill_gap'],
            'predicted_training_recommendation' => $profile['training_title'],
            'training_type' => $profile['training_type'],
            'provider' => $profile['provider'],
            'track' => $profile['track'],
            'rationale' => "{$priorityLabel} priority development in {$entry->focus_area} indicates a need for {$profile['track']}.",
        ];
    }

    protected function recommendationsFor(Collection $entries): Collection
    {
        return $entries
            ->map(fn (LearningNeedsAnalysis $entry) => $this->recommendationProfile($entry))
            ->unique(fn (array $recommendation) => $recommendation['focus_area'].'|'.$recommendation['predicted_training_recommendation'])
            ->values();
    }

    protected function employeeNotifications(Collection $recommendations, Collection $trainings, Collection $lapEntries): Collection
    {
        $notifications = collect();

        foreach ($recommendations as $recommendation) {
            $matchedTraining = $trainings->first(fn (TrainingApplication $training) => $training->training_title === $recommendation['predicted_training_recommendation']);

            if (! $matchedTraining) {
                $notifications->push([
                    'title' => 'Training Recommendation Available',
                    'message' => "You are advised to undergo {$recommendation['predicted_training_recommendation']} to address the prescribed skills gap in {$recommendation['focus_area']}.",
                    'type' => 'action',
                ]);

                continue;
            }

            if ($matchedTraining->status === 'applied') {
                $notifications->push([
                    'title' => 'Training Application Submitted',
                    'message' => "Your application for {$matchedTraining->training_title} has been recorded. Monitor the recommendation status and prepare once scheduled.",
                    'type' => 'info',
                ]);
            }

            if (in_array($matchedTraining->status, ['ongoing', 'completed'], true) || $matchedTraining->is_attended) {
                $notifications->push([
                    'title' => 'Undergo Training Notice',
                    'message' => "Proceed with {$matchedTraining->training_title} and complete all required learning activities.",
                    'type' => 'notice',
                ]);
            }
        }

        foreach ($trainings->filter(fn (TrainingApplication $training) => $training->status === 'completed' || $training->is_attended) as $training) {
            $hasLap = $lapEntries->contains(fn (LearningActionPlan $lap) => $lap->training_title === $training->training_title);

            if (! $hasLap) {
                $notifications->push([
                    'title' => 'Learning Action Plan Required',
                    'message' => "Submit your Learning Action Plan for {$training->training_title} after training completion.",
                    'type' => 'warning',
                ]);
            }
        }

        return $notifications->unique('message')->values();
    }

    public function trainings(Request $request): Response
    {
        $user = $request->user();
        $trainings = TrainingApplication::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $lnaEntries = LearningNeedsAnalysis::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $lapEntries = LearningActionPlan::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $recommendations = $this->recommendationsFor($lnaEntries);

        return Inertia::render('Employee/MyTrainings/Index', [
            'recommendations' => $recommendations->map(function (array $recommendation) use ($trainings) {
                $applied = $trainings->contains(fn (TrainingApplication $training) => $training->training_title === $recommendation['predicted_training_recommendation']);

                return [
                    ...$recommendation,
                    'has_application' => $applied,
                ];
            })->values(),
            'notifications' => $this->employeeNotifications($recommendations, $trainings, $lapEntries),
        ]);
    }

    public function applications(Request $request): Response
    {
        $user = $request->user();
        $trainings = TrainingApplication::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $recommendations = $this->recommendationsFor(
            LearningNeedsAnalysis::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(),
        );

        return Inertia::render('Employee/TrainingApplications/Index', [
            'trainings' => $trainings
                ->map(fn (TrainingApplication $item) => [
                    'id' => $item->id,
                    'training_title' => $item->training_title,
                    'training_type' => $item->training_type,
                    'provider' => $item->provider,
                    'office' => $item->office,
                    'start_date' => $item->start_date?->toDateString(),
                    'end_date' => $item->end_date?->toDateString(),
                    'progress_percent' => $item->progress_percent,
                    'status' => $item->status,
                    'is_attended' => $item->is_attended,
                ]),
            'recommendations' => $recommendations,
        ]);
    }

    public function showApplication(Request $request, TrainingApplication $trainingApplication): Response
    {
        abort_unless($trainingApplication->user_id === $request->user()->id, 404);

        return Inertia::render('Employee/MyTrainings/Show', [
            'training' => [
                'id' => $trainingApplication->id,
                'training_title' => $trainingApplication->training_title,
                'training_type' => $trainingApplication->training_type,
                'provider' => $trainingApplication->provider,
                'office' => $trainingApplication->office,
                'start_date' => $trainingApplication->start_date?->toDateString(),
                'end_date' => $trainingApplication->end_date?->toDateString(),
                'progress_percent' => $trainingApplication->progress_percent,
                'status' => $trainingApplication->status,
                'process_remarks' => $trainingApplication->process_remarks,
                'processed_at' => $trainingApplication->processed_at?->toDateTimeString(),
                'is_attended' => $trainingApplication->is_attended,
                'completed_on' => $trainingApplication->completed_on?->toDateString(),
            ],
        ]);
    }

    public function storeTraining(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'training_title' => ['required', 'string', 'max:255'],
            'training_type' => ['required', 'string', 'in:Invitational,In-house'],
            'provider' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:applied,ongoing,completed'],
            'is_attended' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        TrainingApplication::query()->create([
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'training_title' => $validated['training_title'],
            'training_type' => $validated['training_type'],
            'provider' => $validated['provider'] ?? null,
            'office' => $validated['office'] ?? $user->office,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'progress_percent' => $validated['progress_percent'] ?? 0,
            'status' => $validated['status'] ?? 'applied',
            'is_attended' => (bool) ($validated['is_attended'] ?? false),
            'completed_on' => ($validated['status'] ?? null) === 'completed' ? now()->toDateString() : null,
        ]);

        return back()->with('success', 'Training application submitted successfully.');
    }

    public function learningNeedsAnalysis(Request $request): Response
    {
        $user = $request->user();
        $entries = LearningNeedsAnalysis::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $recommendations = $this->recommendationsFor($entries);

        return Inertia::render('Employee/LearningNeedsAnalysis/Index', [
            'lnaEntries' => $entries
                ->map(fn (LearningNeedsAnalysis $item) => [
                    'id' => $item->id,
                    'focus_area' => $item->focus_area,
                    'competency_gap' => $item->competency_gap,
                    'proposed_intervention' => $item->proposed_intervention,
                    'priority_level' => $item->priority_level,
                    'status' => $item->status,
                    'submitted_on' => $item->submitted_on?->toDateString(),
                    ...$this->recommendationProfile($item),
                ]),
            'recommendations' => $recommendations,
        ]);
    }

    public function learningActionPlan(Request $request): Response
    {
        $user = $request->user();
        $completedTrainings = TrainingApplication::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhere('is_attended', true);
            })
            ->latest()
            ->get();

        return Inertia::render('Employee/LearningActionPlan/Index', [
            'lapEntries' => LearningActionPlan::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get()
                ->map(fn (LearningActionPlan $item) => [
                    'id' => $item->id,
                    'training_title' => $item->training_title,
                    'implementation_summary' => $item->implementation_summary,
                    'learning_outcomes' => $item->learning_outcomes,
                    'status' => $item->status,
                    'submitted_on' => $item->submitted_on?->toDateString(),
                ]),
            'completedTrainings' => $completedTrainings->map(fn (TrainingApplication $item) => [
                'id' => $item->id,
                'training_title' => $item->training_title,
                'training_type' => $item->training_type,
                'completed_on' => $item->completed_on?->toDateString(),
            ])->values(),
        ]);
    }

    public function storeLna(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'focus_area' => ['required', 'string', 'max:255'],
            'competency_gap' => ['required', 'string'],
            'proposed_intervention' => ['required', 'string'],
            'priority_level' => ['required', 'string', 'in:low,medium,high'],
        ]);

        $user = $request->user();

        LearningNeedsAnalysis::query()->create([
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'focus_area' => $validated['focus_area'],
            'competency_gap' => $validated['competency_gap'],
            'proposed_intervention' => $validated['proposed_intervention'],
            'priority_level' => $validated['priority_level'],
            'status' => 'submitted',
            'submitted_on' => now()->toDateString(),
        ]);

        return back()->with('success', 'LNA assessment submitted successfully. Your prescribed skills gap and predicted training recommendation are now available.');
    }

    public function storeLap(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'training_title' => ['required', 'string', 'max:255'],
            'implementation_summary' => ['required', 'string'],
            'learning_outcomes' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,submitted,completed'],
        ]);

        $user = $request->user();

        LearningActionPlan::query()->create([
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'training_title' => $validated['training_title'],
            'implementation_summary' => $validated['implementation_summary'],
            'learning_outcomes' => $validated['learning_outcomes'] ?? null,
            'status' => $validated['status'],
            'submitted_on' => in_array($validated['status'], ['submitted', 'completed'], true) ? now()->toDateString() : null,
        ]);

        return back()->with('success', 'Learning Action Plan saved successfully.');
    }

    public function history(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Employee/History/Index', [
            'history' => TrainingApplication::query()
                ->where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('is_attended', true)
                        ->orWhere('status', 'completed');
                })
                ->latest()
                ->get()
                ->map(fn (TrainingApplication $item) => [
                    'id' => $item->id,
                    'training_title' => $item->training_title,
                    'training_type' => $item->training_type,
                    'status' => $item->status,
                    'progress_percent' => $item->progress_percent,
                    'completed_on' => $item->completed_on?->toDateString(),
                ]),
        ]);
    }
}
