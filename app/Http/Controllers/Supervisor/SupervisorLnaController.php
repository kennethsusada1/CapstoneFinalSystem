<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\LearningNeedsAnalysis;
use App\Models\User;
use App\Services\LnaAnalyticsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorLnaController extends Controller
{
    public function index(Request $request): Response
    {
        $supervisor = User::query()
            ->with('employeeRecord')
            ->whereKey($request->user()->getAuthIdentifier())
            ->firstOrFail();
        $teamOffice = $this->effectiveOffice($supervisor);

        $entries = LearningNeedsAnalysis::query()
            ->with(['user.employeeRecord', 'reviewer', 'recommendations'])
            ->latest('submitted_on')
            ->latest('id')
            ->get()
            ->filter(fn (LearningNeedsAnalysis $entry) => $this->canReview($supervisor, $entry))
            ->values();

        return Inertia::render('Supervisor/LearningNeedsAnalysis/Index', [
            'teamOffice' => $teamOffice,
            'supervisorName' => $supervisor->name,
            'summary' => $this->summary($entries),
            'lnaEntries' => $entries->map(function (LearningNeedsAnalysis $entry) {
                return [
                    'id' => $entry->id,
                    'employee_name' => $entry->user->name,
                    'employee_id' => $entry->employee_id,
                    'office' => $this->effectiveOffice($entry->user),
                    'position' => $entry->user->employeeRecord?->position,
                    'core_functions' => $entry->core_functions ?? [],
                    'support_functions' => $entry->support_functions ?? [],
                    'skill_assessments' => $entry->skill_assessments ?? [],
                    'supervisor_skill_assessments' => $entry->supervisor_skill_assessments ?? [],
                    'preferred_learning_methods' => $entry->preferred_learning_methods ?? [],
                    'preferred_learning_methods_other' => $entry->preferred_learning_methods_other,
                    'assessment_methods' => $entry->assessment_methods ?? [],
                    'employee_signature' => $entry->employee_signature,
                    'focus_area' => $entry->focus_area,
                    'competency_gap' => $entry->competency_gap,
                    'proposed_intervention' => $entry->proposed_intervention,
                    'priority_level' => $entry->priority_level,
                    'status' => $entry->status,
                    'submitted_on' => $entry->submitted_on?->toDateString(),
                    'supervisor_assessment_methods' => $entry->supervisor_assessment_methods ?? [],
                    'supervisor_signature' => $entry->supervisor_signature,
                    'supervisor_signed_on' => $entry->supervisor_signed_on?->toDateString(),
                    'review_remarks' => $entry->review_remarks,
                    'reviewed_at' => $entry->reviewed_at?->toDateTimeString(),
                    'reviewed_by' => $entry->reviewer?->name,
                    'predictive_skills_gap' => $entry->predictive_skills_gap,
                    'prescriptive_training_recommendation' => $entry->prescriptive_training_recommendation,
                    'training_needed' => $entry->training_needed,
                    'training_need_probability' => $entry->training_need_probability,
                    'analytics_model_version' => $entry->analytics_model_version,
                    'recommendations' => $entry->recommendations->sortBy('rank')->values()->map(fn ($recommendation): array => [
                        'rank' => $recommendation->rank,
                        'competency_name' => $recommendation->competency_name,
                        'probability' => (float) $recommendation->probability,
                        'priority' => $recommendation->priority,
                        'training_title' => $recommendation->training_title,
                        'recommendation_text' => $recommendation->recommendation_text,
                    ])->all(),
                ];
            }),
        ]);
    }

    public function update(
        Request $request,
        LearningNeedsAnalysis $learningNeedsAnalysis,
        LnaAnalyticsService $analyticsService,
    ): RedirectResponse
    {
        $learningNeedsAnalysis->loadMissing('user.employeeRecord');
        $supervisor = User::query()
            ->with('employeeRecord')
            ->whereKey($request->user()->getAuthIdentifier())
            ->firstOrFail();

        abort_unless($this->canReview($supervisor, $learningNeedsAnalysis), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:reviewed,returned'],
            'review_remarks' => [
                $request->string('status')->toString() === 'returned' ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
            'supervisor_skill_assessments' => ['nullable', 'array'],
            'supervisor_skill_assessments.*' => ['required', 'string', 'in:N/A,1,2,3,4'],
            'supervisor_assessment_methods' => ['nullable', 'array'],
            'supervisor_assessment_methods.*' => [
                'string',
                'in:Supervisor Assessment,Questionnaire,Feedback,Observation,Reflection,Customer Feedback,Performance Review,Performance Evaluation (MPOR)',
            ],
            'supervisor_signature' => ['nullable', 'string', 'max:255'],
            'supervisor_signed_on' => ['nullable', 'date'],
        ]);

        $learningNeedsAnalysis->fill([
            'status' => $validated['status'],
            'review_remarks' => $validated['review_remarks'] ?? null,
            'supervisor_skill_assessments' => $validated['supervisor_skill_assessments'] ?? [],
            'supervisor_assessment_methods' => $validated['supervisor_assessment_methods'] ?? [],
            'supervisor_signature' => $validated['supervisor_signature'] ?? $supervisor->name,
            'supervisor_signed_on' => $validated['supervisor_signed_on'] ?? now()->toDateString(),
            'reviewed_by' => $supervisor->id,
            'reviewed_at' => now(),
        ]);

        if ($validated['status'] === 'reviewed') {
            $analytics = $analyticsService->generate($learningNeedsAnalysis);
            $learningNeedsAnalysis->fill([
                'predictive_skills_gap' => $analytics['predictive_skills_gap'],
                'prescriptive_training_recommendation' => $analytics['prescriptive_training_recommendation'],
                'training_needed' => $analytics['training_needed'],
                'training_need_probability' => $analytics['training_need_probability'],
                'analytics_model_version' => $analytics['analytics_model_version'],
                'analytics_generated_at' => now(),
            ]);
        } else {
            $analytics = ['recommendations' => []];
            $learningNeedsAnalysis->fill([
                'predictive_skills_gap' => null,
                'prescriptive_training_recommendation' => null,
                'training_needed' => null,
                'training_need_probability' => null,
                'analytics_model_version' => null,
                'analytics_generated_at' => null,
            ]);
        }

        $learningNeedsAnalysis->save();
        $learningNeedsAnalysis->recommendations()->delete();
        $learningNeedsAnalysis->recommendations()->createMany($analytics['recommendations']);

        $message = $validated['status'] === 'reviewed'
            ? 'The employee LNA assessment has been marked as reviewed.'
            : 'The employee LNA assessment has been returned with remarks.';

        return back()->with('success', $message);
    }

    private function canReview(User $supervisor, LearningNeedsAnalysis $entry): bool
    {
        $employee = $entry->user;

        return $employee->hasRole('employee');
    }

    private function effectiveOffice(User $user): ?string
    {
        $office = trim((string) ($user->office ?: $user->employeeRecord?->office));

        return $office !== '' ? $office : null;
    }

    /**
     * @param  EloquentCollection<int, LearningNeedsAnalysis>  $entries
     * @return array<string, int>
     */
    private function summary(EloquentCollection $entries): array
    {
        $employees = $entries->pluck('user_id')->unique()->count();
        $reviewed = $entries->where('status', 'reviewed')->count();
        $returned = $entries->where('status', 'returned')->count();

        return [
            'total_assessments' => $entries->count(),
            'employees_assessed' => $employees,
            'high_priority' => $entries->where('priority_level', 'high')->count(),
            'pending_review' => $entries->where('status', 'submitted')->count(),
            'reviewed' => $reviewed,
            'returned' => $returned,
            'completion_rate' => $entries->isEmpty()
                ? 0
                : (int) round((($reviewed + $returned) / $entries->count()) * 100),
        ];
    }

}
