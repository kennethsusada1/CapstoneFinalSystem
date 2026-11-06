<?php

namespace App\Http\Controllers\Secretariat;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningDevelopmentPlan;
use App\Models\ProposedTrainingProgram;
use App\Models\TrainingApplication;
use App\Models\TrainingProgram;
use App\Models\TrainingReferral;
use App\Models\User;
use App\Services\PmsCallbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SecretariatPortalController extends Controller
{
    public function dashboard(): Response
    {
        $applications = TrainingApplication::query()->with('user')->latest()->get();
        $plans = LearningActionPlan::query()->with('user')->latest()->get();
        $ldPlans = LearningDevelopmentPlan::query()->latest()->get();

        return Inertia::render('Secretariat/Dashboard', [
            'stats' => $this->stats($applications, $plans, $ldPlans),
            'recentApplications' => $this->applicationRows($applications->take(6)),
            'recentPlans' => $plans->take(5)->map(fn (LearningActionPlan $plan) => $this->lapRow($plan))->values(),
            'programs' => $this->programCatalog(),
            'activityMix' => [
                ['label' => 'Pending processing', 'value' => $applications->where('secretariat_status', 'pending')->count(), 'color' => '#facc15'],
                ['label' => 'Processed / for HRDC', 'value' => $applications->where('secretariat_status', 'processed')->where('status', 'applied')->count(), 'color' => '#38bdf8'],
                ['label' => 'Completed', 'value' => $applications->where('status', 'completed')->count(), 'color' => '#34d399'],
            ],
        ]);
    }

    public function applications(): Response
    {
        $applications = TrainingApplication::query()
            ->with(['user', 'learningNeedsAnalysis.reviewer', 'learningDevelopmentPlan'])
            ->latest()
            ->get();

        return Inertia::render('Secretariat/Applications/Index', [
            'applications' => $this->applicationRows($applications),
            'programs' => $this->programCatalog(),
        ]);
    }

    public function trainingPrograms(): Response
    {
        return Inertia::render('Secretariat/TrainingPrograms/Index', [
            'programs' => $this->trainingProgramRows(TrainingProgram::query()->withCount([
                'applications as claimed_slots' => fn ($query) => $query->where('status', '!=', 'rejected'),
            ])->orderByDesc('created_at')->get()),
        ]);
    }

    public function storeTrainingProgram(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->trainingProgramRules($request));
        $validated['created_by'] = $request->user()->id;

        TrainingProgram::query()->create($validated);

        return back()->with('success', 'Training program saved to the catalog.');
    }

    public function updateTrainingProgram(Request $request, TrainingProgram $trainingProgram): RedirectResponse
    {
        $validated = $request->validate($this->trainingProgramRules($request));
        DB::transaction(function () use ($trainingProgram, $validated): void {
            $lockedProgram = TrainingProgram::query()->whereKey($trainingProgram->id)->lockForUpdate()->firstOrFail();
            $applications = $lockedProgram->applications();
            $claimedSeats = (clone $applications)->where('status', '!=', 'rejected')->count();

            if ($validated['slots'] < $claimedSeats) {
                throw ValidationException::withMessages([
                    'slots' => "The capacity cannot be lower than the {$claimedSeats} current application(s).",
                ]);
            }

            if ((clone $applications)->exists() && $validated['title'] !== $lockedProgram->title) {
                throw ValidationException::withMessages([
                    'title' => 'A program title cannot change after applications have been linked to it.',
                ]);
            }

            $lockedProgram->update($validated);
            $lockedProgram->applications()
                ->whereNotIn('status', ['rejected', 'completed'])
                ->update([
                    'training_type' => $lockedProgram->training_type,
                    'delivery_mode' => $lockedProgram->delivery_mode,
                    'provider' => $lockedProgram->provider,
                    'venue' => $lockedProgram->venue,
                    'start_date' => $lockedProgram->start_date,
                    'end_date' => $lockedProgram->end_date,
                ]);
        });

        return back()->with('success', 'Training program details updated.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function trainingProgramRules(Request $request): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'provider' => ['nullable', 'string', 'max:255'],
            'training_type' => ['required', Rule::in(['Invitational', 'In-house'])],
            'delivery_mode' => ['required', Rule::in(['Face-to-face', 'Online', 'Hybrid'])],
            'venue' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => $request->filled('start_date')
                ? ['nullable', 'date', 'after_or_equal:start_date']
                : ['nullable', 'date'],
            'slots' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
        ];
    }

    public function processApplication(Request $request, TrainingApplication $trainingApplication): RedirectResponse
    {
        $wasCompleted = $trainingApplication->status === 'completed';
        $validated = $request->validate([
            'secretariat_status' => ['required', Rule::in(['pending', 'processed', 'returned'])],
            'activity_status' => ['nullable', Rule::in(['ongoing', 'completed'])],
            'training_program_id' => ['nullable', 'integer', 'exists:training_programs,id'],
            'process_remarks' => [
                $request->string('secretariat_status')->toString() === 'returned' ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $updates = [
            'secretariat_status' => $validated['secretariat_status'],
            'process_remarks' => $validated['process_remarks'] ?? null,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ];

        DB::transaction(function () use ($validated, $trainingApplication, &$updates): void {
            if ($validated['secretariat_status'] === 'returned') {
                if ($trainingApplication->status !== 'applied') {
                    throw ValidationException::withMessages([
                        'secretariat_status' => 'Only an application awaiting program approval can be returned to the employee.',
                    ]);
                }

                if ($trainingApplication->learningDevelopmentPlan()->exists()) {
                    throw ValidationException::withMessages([
                        'secretariat_status' => 'An application cannot be returned after its L&D Plan has been created.',
                    ]);
                }

                $updates = [
                    ...$updates,
                    'training_program_id' => null,
                    'delivery_mode' => null,
                    'provider' => null,
                    'venue' => null,
                    'start_date' => null,
                    'end_date' => null,
                ];
            }

            if ($validated['secretariat_status'] !== 'returned'
                && filled($validated['training_program_id'] ?? null)
                && (int) $validated['training_program_id'] !== (int) $trainingApplication->training_program_id) {
                if ($validated['secretariat_status'] !== 'processed') {
                    throw ValidationException::withMessages([
                        'training_program_id' => 'A catalog program can only be assigned when the application is marked processed.',
                    ]);
                }

                if ($trainingApplication->learningDevelopmentPlan()->exists()) {
                    throw ValidationException::withMessages([
                        'training_program_id' => 'A program cannot be changed after its L&D Plan has been created.',
                    ]);
                }

                $program = TrainingProgram::query()
                    ->openForEnrollment()
                    ->whereKey($validated['training_program_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $program) {
                    throw ValidationException::withMessages([
                        'training_program_id' => 'Choose a program that is currently published.',
                    ]);
                }

                $claimedSeats = $program->applications()
                    ->where('status', '!=', 'rejected')
                    ->where('training_applications.id', '!=', $trainingApplication->id)
                    ->count();

                if ($claimedSeats >= $program->slots) {
                    throw ValidationException::withMessages([
                        'training_program_id' => 'This program has no remaining seats.',
                    ]);
                }

                $updates = [
                    ...$updates,
                    'training_program_id' => $program->id,
                    'training_title' => $program->title,
                    'training_type' => $program->training_type,
                    'delivery_mode' => $program->delivery_mode,
                    'provider' => $program->provider,
                    'venue' => $program->venue,
                    'start_date' => $program->start_date,
                    'end_date' => $program->end_date,
                ];
            }

            if (isset($validated['activity_status']) && in_array($trainingApplication->status, ['ongoing', 'completed'], true)) {
                $updates['status'] = $validated['activity_status'];
                $updates['completed_on'] = $validated['activity_status'] === 'completed'
                    ? ($trainingApplication->completed_on ?? now()->toDateString())
                    : null;
            }

            $trainingApplication->update($updates);
        });

        // ---------------------------------------------------------------
        // If this application is linked to a PMS referral and has just
        // been marked completed, fire the outbound callback to smart-pms
        // so the employee's PMS account gets unlocked.
        // The callback is best-effort — failure is logged and stored on
        // the referral (pms_notify_error) but does not fail this request.
        // ---------------------------------------------------------------
        if (! $wasCompleted && ($validated['activity_status'] ?? null) === 'completed' && $trainingApplication->training_referral_id !== null) {
            $referral = $trainingApplication->trainingReferral;

            if ($referral !== null) {
                $completedOn = $trainingApplication->completed_on?->toIso8601String()
                    ?? now()->toIso8601String();

                app(PmsCallbackService::class)->notifyComplete(
                    referral: $referral,
                    completedAt: $completedOn,
                    coursesCompleted: $this->completedCourses($referral),
                    trainerRemarks: $validated['process_remarks'] ?? null,
                );
            }
        }

        return back()->with('success', $validated['secretariat_status'] === 'processed'
            ? 'Training application processed. The Secretariat can now prepare its L&D Plan for HRDC.'
            : 'Training application processing status updated.');
    }

    public function ldPlans(): Response
    {
        return Inertia::render('Secretariat/LearningDevelopmentPlans/Index', [
            'plans' => LearningDevelopmentPlan::query()
                ->with(['submitter', 'trainingApplication.user'])
                ->latest()
                ->get()
                ->map(fn (LearningDevelopmentPlan $plan) => $this->ldPlanRow($plan)),
            'processedApplications' => TrainingApplication::query()
                ->with('user')
                ->where('secretariat_status', 'processed')
                ->where('status', 'applied')
                ->whereDoesntHave('learningDevelopmentPlan')
                ->latest('processed_at')
                ->get()
                ->map(fn (TrainingApplication $application) => $this->applicationRow($application)),
            'programs' => $this->programCatalog(),
            'currentYear' => now()->year,
        ]);
    }

    public function storeLdPlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'training_application_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'planning_year' => ['required', 'digits:4'],
            'objectives' => ['required', 'string', 'max:5000'],
            'priority_programs' => ['required', 'string', 'max:5000'],
            'budget_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['draft', 'submitted'])],
        ]);

        $application = TrainingApplication::query()
            ->whereKey($validated['training_application_id'])
            ->where('secretariat_status', 'processed')
            ->where('status', 'applied')
            ->whereDoesntHave('learningDevelopmentPlan')
            ->first();

        if (! $application) {
            return back()->withErrors([
                'training_application_id' => 'Select a processed training application that does not yet have an L&D Plan.',
            ]);
        }

        $plan = LearningDevelopmentPlan::query()->create([
            ...$validated,
            'training_application_id' => $application->id,
            'submitted_by' => $request->user()->id,
            'submitted_at' => $validated['status'] === 'submitted' ? now() : null,
        ]);

        ProposedTrainingProgram::query()->create([
            'learning_development_plan_id' => $plan->id,
            'title' => $application->training_title,
            'status' => 'pending',
        ]);

        return back()->with('success', $validated['status'] === 'submitted'
            ? 'Learning and Development Plan submitted to HRDC for the proposed training program decision.'
            : 'Learning and Development Plan saved as draft.');
    }

    public function trainingMonitor(): Response
    {
        $applications = TrainingApplication::query()
            ->with('user')
            ->whereIn('status', ['ongoing', 'completed'])
            ->latest()
            ->get();

        return Inertia::render('Secretariat/TrainingMonitor/Index', [
            'activities' => $this->applicationRows($applications),
            'programs' => $this->programCatalog(),
        ]);
    }

    public function updateTrainingMonitor(Request $request, TrainingApplication $trainingApplication): RedirectResponse
    {
        abort_unless(in_array($trainingApplication->status, ['ongoing', 'completed'], true), 404);
        $wasCompleted = $trainingApplication->status === 'completed';

        $validated = $request->validate([
            'activity_status' => ['required', Rule::in(['ongoing', 'completed'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => $request->filled('start_date')
                ? ['nullable', 'date', 'after_or_equal:start_date']
                : ['nullable', 'date'],
            'progress_percent' => ['required', 'integer', 'between:0,100'],
            'is_attended' => ['required', 'boolean'],
        ]);

        if ($trainingApplication->status === 'completed' && $validated['activity_status'] === 'ongoing') {
            return back()->withErrors(['activity_status' => 'A completed training record cannot be reopened.']);
        }

        $completed = $validated['activity_status'] === 'completed';
        $trainingApplication->update([
            'status' => $validated['activity_status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'progress_percent' => $completed ? 100 : $validated['progress_percent'],
            'is_attended' => $completed || $validated['is_attended'],
            'completed_on' => $completed ? ($trainingApplication->completed_on ?? now()->toDateString()) : null,
        ]);

        if (! $wasCompleted && $completed && $trainingApplication->training_referral_id !== null) {
            $referral = $trainingApplication->trainingReferral;

            if ($referral !== null) {
                app(PmsCallbackService::class)->notifyComplete(
                    referral: $referral,
                    completedAt: $trainingApplication->completed_on?->toIso8601String() ?? now()->toIso8601String(),
                    coursesCompleted: $this->completedCourses($referral),
                    trainerRemarks: null,
                );
            }
        }

        return back()->with('success', 'Training schedule and completion status updated.');
    }

    public function lapSubmissions(): Response
    {
        return Inertia::render('Secretariat/LapSubmissions/Index', [
            'plans' => LearningActionPlan::query()->with('user')->latest()->get()->map(fn (LearningActionPlan $plan) => $this->lapRow($plan)),
        ]);
    }

    public function processLap(Request $request, LearningActionPlan $learningActionPlan): RedirectResponse
    {
        if (! in_array($learningActionPlan->status, ['submitted', 'completed'], true)) {
            return back()->withErrors([
                'receipt_status' => 'Only a submitted Learning Action Plan can be received or returned by the Secretariat.',
            ]);
        }

        $validated = $request->validate([
            'receipt_status' => ['required', Rule::in(['pending', 'received', 'returned'])],
            'receipt_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $learningActionPlan->update([
            'receipt_status' => $validated['receipt_status'],
            'receipt_remarks' => $validated['receipt_remarks'] ?? null,
            'received_by' => $request->user()->id,
            'received_at' => now(),
        ]);

        return back()->with('success', 'Learning Action Plan receipt status updated.');
    }

    public function reports(): Response
    {
        $applications = TrainingApplication::query()
            ->with('user')
            ->whereIn('status', ['ongoing', 'completed'])
            ->latest()
            ->get();
        $plans = LearningActionPlan::query()->with('user')->latest()->get();
        $reportableApplications = $this->reportableApplications($applications, $plans);

        return Inertia::render('Secretariat/Reports/Index', [
            'summary' => $this->reportSummary($applications, $plans),
            'activities' => $reportableApplications->groupBy('training_title')->map(fn ($items, $title) => [
                'training_title' => $title,
                'participants' => $items->count(),
                'approved' => $items->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'average_progress' => (int) round($items->avg('progress_percent') ?? 0),
            ])->values(),
            'offices' => $reportableApplications->groupBy(fn (TrainingApplication $item) => $item->office ?: $item->user->office ?: $item->user->employeeRecord?->office ?: 'Unassigned')
                ->map(fn ($items, $office) => ['office' => $office, 'applications' => $items->count(), 'completed' => $items->where('status', 'completed')->count()])
                ->sortByDesc('applications')
                ->values(),
        ]);
    }

    public function ldPlanReports(Request $request): Response
    {
        $planningYear = trim((string) $request->query('planning_year', (string) now()->year));
        abort_unless(preg_match('/^\d{4}$/', $planningYear) === 1, 422);

        $plans = LearningDevelopmentPlan::query()
            ->with(['submitter', 'trainingApplication.user', 'programs'])
            ->where('planning_year', $planningYear)
            ->latest()
            ->get();
        $availableYears = LearningDevelopmentPlan::query()
            ->select('planning_year')
            ->distinct()
            ->orderByDesc('planning_year')
            ->pluck('planning_year')
            ->map(fn ($year): string => (string) $year)
            ->push((string) now()->year)
            ->unique()
            ->values();

        return Inertia::render('Secretariat/Reports/LdPlans', [
            'planningYear' => $planningYear,
            'availableYears' => $availableYears,
            'summary' => [
                'plans' => $plans->count(),
                'submitted' => $plans->where('status', 'submitted')->count(),
                'drafts' => $plans->where('status', 'draft')->count(),
                'approved' => $plans->where('review_status', 'approved')->count(),
                'under_review' => $plans->where('review_status', 'under-review')->count(),
                'disapproved' => $plans->where('review_status', 'disapproved')->count(),
            ],
            'plans' => $plans->map(fn (LearningDevelopmentPlan $plan): array => $this->ldPlanReportRow($plan)),
        ]);
    }

    public function exportLdPlanReport(Request $request): HttpResponse
    {
        $planningYear = trim((string) $request->query('planning_year', (string) now()->year));
        abort_unless(preg_match('/^\d{4}$/', $planningYear) === 1, 422);
        $plans = LearningDevelopmentPlan::query()
            ->with(['submitter', 'trainingApplication.user', 'programs'])
            ->where('planning_year', $planningYear)
            ->latest()
            ->get();

        $lines = [
            'SMART L&D',
            "LEARNING AND DEVELOPMENT PLAN REPORT - {$planningYear}",
            'Generated: '.now()->format('F d, Y h:i A'),
            '',
            'SUMMARY',
            'Total plans: '.$plans->count(),
            'Submitted to HRDC: '.$plans->where('status', 'submitted')->count(),
            'Draft plans: '.$plans->where('status', 'draft')->count(),
            '',
            'PLAN DETAILS',
            'Title | Submitter | Employee | Program(s) | Plan status | HRDC status | Submitted',
        ];

        foreach ($plans as $plan) {
            $lines[] = implode(' | ', [
                $plan->title,
                $plan->submitter->name,
                $plan->trainingApplication?->user->name ?? 'Organization plan',
                $plan->priority_programs,
                ucfirst($plan->status),
                ucfirst($plan->review_status),
                $plan->submitted_at?->toDateString() ?? 'Not submitted',
            ]);
            $lines[] = 'Objectives: '.$plan->objectives;
            if (filled($plan->budget_notes)) {
                $lines[] = 'Budget and implementation notes: '.$plan->budget_notes;
            }
        }

        return response($this->makePdf($lines), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ld-plan-report-'.$planningYear.'.pdf"',
        ]);
    }

    public function exportReport(): HttpResponse
    {
        $applications = TrainingApplication::query()
            ->with('user')
            ->whereIn('status', ['ongoing', 'completed'])
            ->latest()
            ->get();
        $plans = LearningActionPlan::query()->latest()->get();
        $reportableApplications = $this->reportableApplications($applications, $plans);
        $completed = $reportableApplications->count();
        $completionRate = $applications->isEmpty() ? 0 : (int) round(($completed / $applications->count()) * 100);
        $lines = [
            'SMART L&D',
            'TERMINAL TRAINING ACTIVITY REPORT',
            'Generated: '.now()->format('F d, Y h:i A'),
            '',
            'SUMMARY',
            'Approved activities: '.$applications->count(),
            'Reportable completed activities: '.$completed,
            'Terminal report readiness: '.$completionRate.'%',
            '',
            'TRAINING ACTIVITY DETAILS',
            'Employee | Employee ID | Office | Training | Status | Progress | Schedule',
        ];

        foreach ($reportableApplications as $application) {
            $office = $application->office ?: $application->user->office ?: $application->user->employeeRecord?->office ?: 'Unassigned';
            $schedule = ($application->start_date?->toDateString() ?: 'TBA').' - '.($application->end_date?->toDateString() ?: 'TBA');
            $lines[] = implode(' | ', [
                $application->user->name,
                $application->employee_id ?: 'N/A',
                $office,
                $application->training_title,
                ucfirst($application->status),
                $application->progress_percent.'%',
                $schedule,
            ]);
        }

        return response($this->makePdf($lines), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="terminal-training-activity-report.pdf"',
        ]);
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('Secretariat/Profile/Index', [
            'profile' => $this->profileData($request->user()),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($request->user()->id)],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Secretariat profile updated successfully.');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TrainingApplication>  $applications
     * @param  \Illuminate\Database\Eloquent\Collection<int, LearningActionPlan>  $plans
     * @param  \Illuminate\Database\Eloquent\Collection<int, LearningDevelopmentPlan>  $ldPlans
     * @return array<string, int>
     */
    private function stats($applications, $plans, $ldPlans): array
    {
        return [
            'requests' => $applications->count(),
            'pending_requests' => $applications->where('secretariat_status', 'pending')->count(),
            'approved_activities' => $applications->where('status', 'ongoing')->count(),
            'lap_received' => $plans->where('receipt_status', 'received')->count(),
            'ld_plans' => $ldPlans->count(),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TrainingApplication>  $applications
     * @return Collection<int, array<string, mixed>>
     */
    private function applicationRows($applications)
    {
        return $applications->map(fn (TrainingApplication $application) => $this->applicationRow($application))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationRow(TrainingApplication $application): array
    {
        return [
            'id' => $application->id,
            'employee_name' => $application->user->name,
            'employee_id' => $application->employee_id,
            'training_program_id' => $application->training_program_id,
            'office' => $application->office ?: $application->user->office ?: $application->user->employeeRecord?->office,
            'training_title' => $application->training_title,
            'training_type' => $application->training_type,
            'delivery_mode' => $application->delivery_mode,
            'provider' => $application->provider,
            'venue' => $application->venue,
            'start_date' => $application->start_date?->toDateString(),
            'end_date' => $application->end_date?->toDateString(),
            'progress_percent' => $application->progress_percent,
            'status' => $application->status,
            'secretariat_status' => $application->secretariat_status,
            'is_attended' => $application->is_attended,
            'process_remarks' => $application->process_remarks,
            'processed_at' => $application->processed_at?->toDateTimeString(),
            'lna_focus_area' => $application->learningNeedsAnalysis?->focus_area,
            'lna_priority_level' => $application->learningNeedsAnalysis?->priority_level,
            'supervisor_remarks' => $application->learningNeedsAnalysis?->review_remarks,
            'supervisor_reviewed_by' => $application->learningNeedsAnalysis?->reviewer?->name,
            'has_ld_plan' => $application->learningDevelopmentPlan !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lapRow(LearningActionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'employee_name' => $plan->user->name,
            'employee_id' => $plan->employee_id,
            'training_title' => $plan->training_title,
            'implementation_summary' => $plan->implementation_summary,
            'learning_outcomes' => $plan->learning_outcomes,
            'status' => $plan->status,
            'receipt_status' => $plan->receipt_status,
            'receipt_remarks' => $plan->receipt_remarks,
            'submitted_on' => $plan->submitted_on?->toDateString(),
            'received_at' => $plan->received_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ldPlanRow(LearningDevelopmentPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'planning_year' => $plan->planning_year,
            'objectives' => $plan->objectives,
            'priority_programs' => $plan->priority_programs,
            'budget_notes' => $plan->budget_notes,
            'status' => $plan->status,
            'submitted_by' => $plan->submitter->name,
            'submitted_at' => $plan->submitted_at?->toDateTimeString(),
            'training_application_id' => $plan->training_application_id,
            'employee_name' => $plan->trainingApplication?->user?->name,
            'training_title' => $plan->trainingApplication?->training_title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ldPlanReportRow(LearningDevelopmentPlan $plan): array
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
            'employee_name' => $plan->trainingApplication?->user?->name,
            'employee_id' => $plan->trainingApplication?->employee_id,
            'training_title' => $plan->trainingApplication?->training_title,
            'program_decisions' => $plan->programs->map(fn (ProposedTrainingProgram $program): array => [
                'title' => $program->title,
                'status' => $program->status,
                'review_remarks' => $program->review_remarks,
            ])->values(),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TrainingApplication>  $applications
     * @param  \Illuminate\Database\Eloquent\Collection<int, LearningActionPlan>  $plans
     * @return array<string, int>
     */
    private function reportSummary($applications, $plans): array
    {
        $approved = $applications->whereIn('status', ['ongoing', 'completed']);
        $reportable = $this->reportableApplications($approved, $plans);

        return [
            'total_applications' => $reportable->count(),
            'approved_activities' => $approved->count(),
            'completed_activities' => $applications->where('status', 'completed')->count(),
            'lap_submissions' => $plans->count(),
            'lap_received' => $plans->where('receipt_status', 'received')->count(),
            'completion_rate' => $approved->isEmpty() ? 0 : (int) round(($applications->where('status', 'completed')->count() / $approved->count()) * 100),
        ];
    }

    /**
     * @param  Collection<int, TrainingApplication>  $applications
     * @param  Collection<int, LearningActionPlan>  $plans
     * @return Collection<int, TrainingApplication>
     */
    private function reportableApplications(Collection $applications, Collection $plans): Collection
    {
        return $applications
            ->where('status', 'completed')
            ->filter(fn (TrainingApplication $application): bool => $plans->contains(
                fn (LearningActionPlan $plan): bool => $plan->user_id === $application->user_id
                    && ($plan->training_application_id === $application->id
                        || ($plan->training_application_id === null && $plan->training_title === $application->training_title))
                    && $plan->receipt_status === 'received',
            ))
            ->values();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TrainingProgram>  $programs
     * @return list<array{id: int, title: string, category: string, description: string|null, provider: string|null, training_type: string, delivery_mode: string, venue: string|null, start_date: string|null, end_date: string|null, slots: int, claimed_slots: int, remaining_slots: int<0, max>, status: string}>
     */
    private function trainingProgramRows($programs): array
    {
        return array_values($programs->map(fn (TrainingProgram $program): array => [
            'id' => $program->id,
            'title' => $program->title,
            'category' => $program->category,
            'description' => $program->description,
            'provider' => $program->provider,
            'training_type' => $program->training_type,
            'delivery_mode' => $program->delivery_mode,
            'venue' => $program->venue,
            'start_date' => $program->start_date?->toDateString(),
            'end_date' => $program->end_date?->toDateString(),
            'slots' => $program->slots,
            'claimed_slots' => $program->claimed_slots,
            'remaining_slots' => max(0, $program->slots - $program->claimed_slots),
            'status' => $program->status,
        ])->all());
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function programCatalog(): array
    {
        return array_values(TrainingProgram::query()
            ->openForEnrollment()
            ->withCount(['applications as claimed_slots' => fn ($query) => $query->where('status', '!=', 'rejected')])
            ->orderBy('start_date')
            ->get()
            ->filter(fn (TrainingProgram $program): bool => $program->claimed_slots < $program->slots)
            ->map(function (TrainingProgram $program): array {
                $schedule = $program->start_date?->format('M j, Y') ?? 'Schedule to be confirmed';

                if ($program->end_date !== null && $program->end_date->toDateString() !== $program->start_date?->toDateString()) {
                    $schedule .= ' - '.$program->end_date->format('M j, Y');
                }

                return [
                    'id' => $program->id,
                    'title' => $program->title,
                    'category' => $program->category,
                    'schedule' => $schedule,
                    'slots' => max(0, $program->slots - $program->claimed_slots),
                    'provider' => $program->provider ?? 'Provider to be confirmed',
                    'mode' => $program->delivery_mode,
                ];
            })
            ->all());
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

    /**
     * @param  list<string>  $lines
     */
    private function makePdf(array $lines): string
    {
        $wrappedLines = collect($lines)
            ->flatMap(fn (string $line) => $line === '' ? [''] : str_split($line, 105))
            ->values()
            ->all();
        $pages = array_chunk($wrappedLines, 42);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];
        $pageNumbers = [];
        $contentNumbers = [];
        $nextObject = 3;

        foreach ($pages as $page) {
            $pageNumbers[] = $nextObject++;
            $contentNumbers[] = $nextObject++;
        }

        $fontNumber = $nextObject;
        $kids = implode(' ', array_map(fn (int $number) => $number.' 0 R', $pageNumbers));
        $objects[2] = '<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageNumbers).' >>';

        foreach ($pages as $index => $page) {
            $content = "BT\n/F1 10 Tf\n50 760 Td\n";

            foreach ($page as $lineIndex => $line) {
                $fontSize = $lineIndex === 0 && $index === 0 ? 16 : (in_array($line, ['SUMMARY', 'TRAINING ACTIVITY DETAILS'], true) ? 11 : 9);
                $content .= '/F1 '.$fontSize." Tf\n(".$this->pdfEscape($line).") Tj\n0 -".($fontSize === 16 ? 25 : 15)." Td\n";
            }

            $content .= 'ET';
            $objects[$pageNumbers[$index]] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 '.$fontNumber.' 0 R >> >> /Contents '.$contentNumbers[$index].' 0 R >>';
            $objects[$contentNumbers[$index]] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
        }

        $objects[$fontNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        for ($number = 1; $number <= $fontNumber; $number++) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$objects[$number]."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($fontNumber + 1)."\n0000000000 65535 f \n";

        for ($number = 1; $number <= $fontNumber; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        return $pdf."trailer\n<< /Size ".($fontNumber + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";
    }

    private function pdfEscape(string $value): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }

    /**
     * @return list<array{course_code: string, title: string, completed_at: string}>
     */
    private function completedCourses(TrainingReferral $referral): array
    {
        $courses = [];

        foreach ($referral->coursesCompleted as $course) {
            if (blank($course->course_code)
                || blank($course->title)
                || $course->completed_at === null) {
                continue;
            }

            $courses[] = [
                'course_code' => $course->course_code,
                'title' => $course->title,
                'completed_at' => $course->completed_at->toIso8601String(),
            ];
        }

        return $courses;
    }
}
