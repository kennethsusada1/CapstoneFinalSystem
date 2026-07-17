<?php

namespace App\Http\Controllers\HRDC;

use App\Http\Controllers\Controller;
use App\Models\LearningDevelopmentPlan;
use App\Models\ProposedTrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HrdcPortalController extends Controller
{
    public function dashboard(): Response
    {
        $plans = $this->submittedPlans();
        $programs = $plans->flatMap->programs;

        return Inertia::render('HRDC/Dashboard', [
            'stats' => [
                'submitted_plans' => $plans->count(),
                'pending_plans' => $plans->whereIn('review_status', ['pending', 'under-review'])->count(),
                'pending_programs' => $programs->where('status', 'pending')->count(),
                'approved_programs' => $programs->where('status', 'approved')->count(),
            ],
            'recentPlans' => $plans->take(5)->map(fn (LearningDevelopmentPlan $plan) => $this->planRow($plan)),
            'pendingPrograms' => $programs->where('status', 'pending')->take(6)->map(fn (ProposedTrainingProgram $program) => $this->programRow($program))->values(),
            'decisionMix' => [
                ['label' => 'Pending', 'value' => $programs->where('status', 'pending')->count(), 'color' => '#facc15'],
                ['label' => 'Approved', 'value' => $programs->where('status', 'approved')->count(), 'color' => '#34d399'],
                ['label' => 'Disapproved', 'value' => $programs->where('status', 'disapproved')->count(), 'color' => '#f87171'],
            ],
        ]);
    }

    public function plans(): Response
    {
        $plans = $this->submittedPlans();

        return Inertia::render('HRDC/LearningDevelopmentPlans/Index', [
            'plans' => $plans->map(fn (LearningDevelopmentPlan $plan) => $this->planRow($plan)),
        ]);
    }

    public function planShow(LearningDevelopmentPlan $learningDevelopmentPlan): Response
    {
        abort_unless($learningDevelopmentPlan->status === 'submitted', 404);
        $this->ensurePrograms($learningDevelopmentPlan);
        $learningDevelopmentPlan->loadMissing(['submitter', 'programs.reviewer']);

        return Inertia::render('HRDC/LearningDevelopmentPlans/Show', [
            'plan' => $this->planRow($learningDevelopmentPlan),
            'programs' => $learningDevelopmentPlan->programs->map(fn (ProposedTrainingProgram $program) => $this->programRow($program))->values(),
        ]);
    }

    public function receivePlan(Request $request, LearningDevelopmentPlan $learningDevelopmentPlan): RedirectResponse
    {
        abort_unless($learningDevelopmentPlan->status === 'submitted', 404);

        if (in_array($learningDevelopmentPlan->review_status, ['approved', 'disapproved', 'partially-approved'], true)) {
            return back()->with('success', 'This L&D Plan already has finalized program decisions.');
        }

        $validated = $request->validate([
            'review_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $learningDevelopmentPlan->update([
            'review_status' => 'under-review',
            'review_remarks' => $validated['review_remarks'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'L&D Plan marked as received and under HRDC review.');
    }

    public function programApprovals(): Response
    {
        $plans = $this->submittedPlans();

        return Inertia::render('HRDC/ProgramApprovals/Index', [
            'programs' => $plans->flatMap->programs
                ->sortByDesc('id')
                ->map(fn (ProposedTrainingProgram $program) => $this->programRow($program))
                ->values(),
        ]);
    }

    public function reviewProgram(Request $request, ProposedTrainingProgram $proposedTrainingProgram): RedirectResponse
    {
        $proposedTrainingProgram->loadMissing('plan');
        abort_unless($proposedTrainingProgram->plan->status === 'submitted', 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'disapproved'])],
            'review_remarks' => [
                $request->string('status')->toString() === 'disapproved' ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $proposedTrainingProgram->update([
            'status' => $validated['status'],
            'review_remarks' => $validated['review_remarks'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->refreshPlanStatus($proposedTrainingProgram->plan);

        return back()->with('success', 'Proposed training program decision saved.');
    }

    public function reports(): Response
    {
        $plans = $this->submittedPlans();
        $programs = $plans->flatMap->programs;

        return Inertia::render('HRDC/Reports/Index', [
            'summary' => [
                'plans_received' => $plans->count(),
                'programs_reviewed' => $programs->whereIn('status', ['approved', 'disapproved'])->count(),
                'approved' => $programs->where('status', 'approved')->count(),
                'disapproved' => $programs->where('status', 'disapproved')->count(),
                'approval_rate' => $programs->whereIn('status', ['approved', 'disapproved'])->isEmpty()
                    ? 0
                    : (int) round(($programs->where('status', 'approved')->count() / $programs->whereIn('status', ['approved', 'disapproved'])->count()) * 100),
            ],
            'planResults' => $plans->map(fn (LearningDevelopmentPlan $plan) => [
                'id' => $plan->id,
                'title' => $plan->title,
                'planning_year' => $plan->planning_year,
                'review_status' => $plan->review_status,
                'total_programs' => $plan->programs->count(),
                'approved' => $plan->programs->where('status', 'approved')->count(),
                'disapproved' => $plan->programs->where('status', 'disapproved')->count(),
                'pending' => $plan->programs->where('status', 'pending')->count(),
            ])->values(),
        ]);
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('HRDC/Profile/Index', [
            'profile' => $this->profileData($this->hrdcUser($request)),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->hrdcUser($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return back()->with('success', 'HRDC profile updated successfully.');
    }

    /**
     * @return EloquentCollection<int, LearningDevelopmentPlan>
     */
    private function submittedPlans(): EloquentCollection
    {
        $plans = LearningDevelopmentPlan::query()
            ->where('status', 'submitted')
            ->with(['submitter', 'programs.reviewer'])
            ->latest('submitted_at')
            ->get();

        $plans->each(fn (LearningDevelopmentPlan $plan) => $this->ensurePrograms($plan));

        return $plans->load(['submitter', 'programs.reviewer']);
    }

    private function ensurePrograms(LearningDevelopmentPlan $plan): void
    {
        if ($plan->programs()->exists()) {
            return;
        }

        collect(preg_split('/\r\n|\r|\n|;/', $plan->priority_programs) ?: [])
            ->map(fn (string $title) => trim($title))
            ->filter()
            ->unique()
            ->each(fn (string $title) => $plan->programs()->create([
                'title' => $title,
                'status' => 'pending',
            ]));
    }

    private function refreshPlanStatus(LearningDevelopmentPlan $plan): void
    {
        $programs = $plan->programs()->get();
        $status = match (true) {
            $programs->isEmpty() || $programs->contains('status', 'pending') => 'under-review',
            $programs->every(fn (ProposedTrainingProgram $program) => $program->status === 'approved') => 'approved',
            $programs->every(fn (ProposedTrainingProgram $program) => $program->status === 'disapproved') => 'disapproved',
            default => 'partially-approved',
        };

        $plan->update([
            'review_status' => $status,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function planRow(LearningDevelopmentPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'planning_year' => $plan->planning_year,
            'objectives' => $plan->objectives,
            'priority_programs' => $plan->priority_programs,
            'budget_notes' => $plan->budget_notes,
            'status' => $plan->status,
            'review_status' => $plan->review_status,
            'review_remarks' => $plan->review_remarks,
            'submitted_by' => $plan->submitter->name,
            'submitted_at' => $plan->submitted_at?->toDateTimeString(),
            'program_count' => $plan->programs->count(),
            'approved_count' => $plan->programs->where('status', 'approved')->count(),
            'disapproved_count' => $plan->programs->where('status', 'disapproved')->count(),
            'pending_count' => $plan->programs->where('status', 'pending')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function programRow(ProposedTrainingProgram $program): array
    {
        return [
            'id' => $program->id,
            'plan_id' => $program->learning_development_plan_id,
            'plan_title' => $program->plan->title,
            'planning_year' => $program->plan->planning_year,
            'submitted_by' => $program->plan->submitter->name,
            'title' => $program->title,
            'status' => $program->status,
            'review_remarks' => $program->review_remarks,
            'reviewed_by' => $program->reviewer?->name,
            'reviewed_at' => $program->reviewed_at?->toDateTimeString(),
        ];
    }

    private function hrdcUser(Request $request): User
    {
        return User::query()->with('employeeRecord')->whereKey($request->user()->getAuthIdentifier())->firstOrFail();
    }

    /**
     * @return array<string, string|null>
     */
    private function profileData(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'office' => $user->office ?: $user->employeeRecord?->office,
            'position' => $user->employeeRecord?->position,
            'address' => $user->address,
        ];
    }
}
