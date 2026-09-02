<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use App\Services\LnaAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLearningController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    protected function recommendationProfile(LearningNeedsAnalysis $entry): array
    {
        return app(LnaAnalyticsService::class)->recommendation($entry);
    }

    /**
     * @param  Collection<int, LearningNeedsAnalysis>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    protected function recommendationsFor(Collection $entries): Collection
    {
        return $entries
            ->filter(fn (LearningNeedsAnalysis $entry): bool => $entry->status === 'reviewed'
                && $entry->analytics_generated_at !== null
                && $entry->predictive_skills_gap !== null
                && $entry->prescriptive_training_recommendation !== null)
            ->map(fn (LearningNeedsAnalysis $entry) => $this->recommendationProfile($entry))
            ->unique(fn (array $recommendation) => $recommendation['focus_area'].'|'.$recommendation['prescriptive_training_recommendation'])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $recommendations
     * @param  Collection<int, TrainingApplication>  $trainings
     * @param  Collection<int, LearningActionPlan>  $lapEntries
     * @return Collection<int, array<string, string>>
     */
    protected function employeeNotifications(Collection $recommendations, Collection $trainings, Collection $lapEntries): Collection
    {
        $notifications = collect();

        foreach ($recommendations as $recommendation) {
            $matchedTraining = $trainings->first(fn (TrainingApplication $training) => $training->training_title === $recommendation['prescriptive_training_recommendation']);

            if (! $matchedTraining) {
                $notifications->push([
                    'title' => 'Training Recommendation Available',
                    'message' => "You are advised to undergo {$recommendation['prescriptive_training_recommendation']} to address the predictive skills gap in {$recommendation['focus_area']}.",
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
            ->with('recommendations')
            ->latest()
            ->get();
        $lapEntries = LearningActionPlan::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
        $recommendations = $this->recommendationsFor($lnaEntries);

        return Inertia::render('Employee/MyTrainings/Index', [
            'recommendations' => $recommendations->map(function (array $recommendation) use ($trainings) {
                $applied = $trainings->contains(fn (TrainingApplication $training) => $training->training_title === $recommendation['prescriptive_training_recommendation']);

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
                ->with('recommendations')
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
                    'secretariat_status' => $item->secretariat_status,
                    'is_attended' => $item->is_attended,
                ]),
            'recommendations' => $recommendations
                ->reject(fn (array $recommendation) => $trainings->contains(
                    fn (TrainingApplication $training) => $training->learning_needs_analysis_id === $recommendation['lna_id'],
                ))
                ->values(),
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
                'secretariat_status' => $trainingApplication->secretariat_status,
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
            'lna_id' => ['required', 'integer'],
            'training_type' => ['required', 'string', 'in:Invitational,In-house'],
            'provider' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $lna = LearningNeedsAnalysis::query()
            ->whereKey($validated['lna_id'])
            ->where('user_id', $user->id)
            ->where('status', 'reviewed')
            ->whereNotNull('analytics_generated_at')
            ->whereNotNull('predictive_skills_gap')
            ->whereNotNull('prescriptive_training_recommendation')
            ->with('recommendations')
            ->first();

        if (! $lna) {
            return back()->withErrors([
                'lna_id' => 'Select an LNA assessment with supervisor-generated analytics.',
            ]);
        }

        $alreadyApplied = TrainingApplication::query()
            ->where('user_id', $user->id)
            ->where('learning_needs_analysis_id', $lna->id)
            ->exists();

        if ($alreadyApplied) {
            return back()->withErrors([
                'lna_id' => 'A training application has already been submitted for this reviewed LNA assessment.',
            ]);
        }

        $recommendation = $this->recommendationProfile($lna);

        TrainingApplication::query()->create([
            'user_id' => $user->id,
            'learning_needs_analysis_id' => $lna->id,
            'employee_id' => $user->employee_id,
            'training_title' => $recommendation['prescriptive_training_recommendation'],
            'training_type' => $validated['training_type'],
            'provider' => $validated['provider'] ?? null,
            'office' => $validated['office'] ?? $user->office,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'progress_percent' => 0,
            'status' => 'applied',
            'secretariat_status' => 'pending',
            'is_attended' => false,
        ]);

        return back()->with('success', 'Training application submitted to the Secretariat for processing.');
    }

    public function learningNeedsAnalysis(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('employeeRecord');
        $entries = LearningNeedsAnalysis::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('Employee/LearningNeedsAnalysis/Index', [
            'employeeProfile' => [
                'name' => $user->name,
                'position' => $user->employeeRecord?->position,
                'department' => $user->office ?: $user->employeeRecord?->office,
                'employee_id' => $user->employee_id,
            ],
            'lnaEntries' => $entries
                ->map(fn (LearningNeedsAnalysis $item) => [
                    'id' => $item->id,
                    'ipcr_rating' => $item->ipcr_rating,
                    'core_functions' => $item->core_functions,
                    'support_functions' => $item->support_functions,
                    'skill_assessments' => $item->skill_assessments,
                    'preferred_learning_methods' => $item->preferred_learning_methods,
                    'preferred_learning_methods_other' => $item->preferred_learning_methods_other,
                    'assessment_methods' => $item->assessment_methods,
                    'employee_signature' => $item->employee_signature,
                    'status' => $item->status,
                    'submitted_on' => $item->submitted_on?->toDateString(),
                ]),
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
                    'training_application_id' => $item->training_application_id,
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
        if ($request->boolean('workbook_form')) {
            $validated = $request->validate([
                'workbook_form' => ['required', 'boolean'],
                'ipcr_rating' => ['nullable', 'string', 'max:50'],
                'core_functions' => ['required', 'array', 'size:6'],
                'core_functions.*' => ['nullable', 'string', 'max:1000'],
                'support_functions' => ['required', 'array', 'size:4'],
                'support_functions.*' => ['nullable', 'string', 'max:1000'],
                'skill_assessments' => ['required', 'array', 'min:1'],
                'skill_assessments.*' => ['required', 'string', 'in:N/A,1,2,3,4'],
                'preferred_learning_methods' => ['required', 'array', 'min:1'],
                'preferred_learning_methods.*' => ['string', 'in:Mentorship/Coaching,Self-paced Learning,Workshops/Seminars/Trainings,Others'],
                'preferred_learning_methods_other' => ['nullable', 'string', 'max:255'],
                'assessment_methods' => ['required', 'array', 'min:1'],
                'assessment_methods.*' => ['string', 'in:Employee Self-Assessment,Questionnaire,Feedback,Observation,Reflection,Customer Feedback,Performance Review,Performance Evaluation (MPOR)'],
                'employee_signature' => ['nullable', 'string', 'max:255'],
            ]);

            $skills = collect($validated['skill_assessments']);
            $gapSkills = $skills
                ->filter(fn (string $rating): bool => in_array($rating, ['N/A', '3', '4'], true) === false);
            $gaps = $gapSkills
                ->map(fn (string $rating, string $skill): string => "{$skill} (self-rating: {$rating})")
                ->values();
            $focusArea = $gapSkills->keys()->first() ?? 'Employee self-assessment';
            $competencyGap = $gaps->isEmpty()
                ? 'No immediate competency gap identified in the selected skills.'
                : 'Employee-identified development needs: '.$gaps->implode(', ').'.';
            $learningMethods = collect($validated['preferred_learning_methods'])
                ->map(fn (string $method): string => $method === 'Others'
                    ? ($validated['preferred_learning_methods_other'] ?? 'Other learning method')
                    : $method);
            $priority = $skills->contains(fn (string $rating): bool => $rating === '1')
                ? 'high'
                : ($skills->contains(fn (string $rating): bool => $rating === '2') ? 'medium' : 'low');

            $user = $request->user();

            LearningNeedsAnalysis::query()->create([
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'ipcr_rating' => $validated['ipcr_rating'] ?? null,
                'core_functions' => $validated['core_functions'],
                'support_functions' => $validated['support_functions'],
                'skill_assessments' => $validated['skill_assessments'],
                'preferred_learning_methods' => $validated['preferred_learning_methods'],
                'preferred_learning_methods_other' => $validated['preferred_learning_methods_other'] ?? null,
                'assessment_methods' => $validated['assessment_methods'],
                'employee_signature' => $validated['employee_signature'] ?? null,
                'focus_area' => $focusArea,
                'competency_gap' => $competencyGap,
                'proposed_intervention' => $learningMethods->implode(', '),
                'priority_level' => $priority,
                'status' => 'submitted',
                'submitted_on' => now()->toDateString(),
            ]);

            return back()->with('success', 'LNA form submitted successfully. Your employee responses are now waiting for supervisor review.');
        }

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

        return back()->with('success', 'LNA assessment submitted successfully. It is now waiting for your supervisor evaluation.');
    }

    public function storeLap(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'training_application_id' => ['required', 'integer'],
            'training_title' => ['required', 'string', 'max:255'],
            'implementation_summary' => ['required', 'string'],
            'learning_outcomes' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,submitted,completed'],
        ]);

        $user = $request->user();
        $eligibleTraining = TrainingApplication::query()
            ->whereKey($validated['training_application_id'])
            ->where('user_id', $user->id)
            ->where('training_title', $validated['training_title'])
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhere('is_attended', true);
            })
            ->first();

        if (! $eligibleTraining) {
            return back()->withErrors([
                'training_title' => 'A LAP can only be submitted after the approved training has been attended or completed.',
            ]);
        }

        $hasExistingLap = LearningActionPlan::query()
            ->where('user_id', $user->id)
            ->where('training_application_id', $eligibleTraining->id)
            ->whereIn('status', ['submitted', 'completed'])
            ->exists();

        if ($hasExistingLap && in_array($validated['status'], ['submitted', 'completed'], true)) {
            return back()->withErrors([
                'training_title' => 'A submitted LAP already exists for this training activity.',
            ]);
        }

        LearningActionPlan::query()->create([
            'user_id' => $user->id,
            'training_application_id' => $eligibleTraining->id,
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
