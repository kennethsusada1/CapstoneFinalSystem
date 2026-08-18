<?php

namespace App\Http\Controllers\Secretariat;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningDevelopmentPlan;
use App\Models\ProposedTrainingProgram;
use App\Models\TrainingApplication;
use App\Models\TrainingReferral;
use App\Models\User;
use App\Services\PmsCallbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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
        ]);
    }

    public function processApplication(Request $request, TrainingApplication $trainingApplication): RedirectResponse
    {
        $validated = $request->validate([
            'secretariat_status' => ['required', Rule::in(['pending', 'processed', 'returned'])],
            'activity_status' => ['nullable', Rule::in(['ongoing', 'completed'])],
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

        if (isset($validated['activity_status']) && in_array($trainingApplication->status, ['ongoing', 'completed'], true)) {
            $updates['status'] = $validated['activity_status'];
            $updates['completed_on'] = $validated['activity_status'] === 'completed'
                ? ($trainingApplication->completed_on ?? now()->toDateString())
                : null;
        }

        $trainingApplication->update($updates);

        // ---------------------------------------------------------------
        // If this application is linked to a PMS referral and has just
        // been marked completed, fire the outbound callback to smart-pms
        // so the employee's PMS account gets unlocked.
        // The callback is best-effort — failure is logged and stored on
        // the referral (pms_notify_error) but does not fail this request.
        // ---------------------------------------------------------------
        if (($validated['activity_status'] ?? null) === 'completed' && $trainingApplication->training_referral_id !== null) {
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
            'office' => $application->office ?: $application->user->office ?: $application->user->employeeRecord?->office,
            'training_title' => $application->training_title,
            'training_type' => $application->training_type,
            'provider' => $application->provider,
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
     * @return list<array<string, int|string>>
     */
    private function programCatalog(): array
    {
        return [
            ['id' => 1, 'title' => 'Supervisory Development Program', 'category' => 'Leadership', 'schedule' => 'August 18-20, 2026', 'slots' => 24, 'provider' => 'HRDC Learning Unit', 'mode' => 'Face-to-face'],
            ['id' => 2, 'title' => 'Digital Productivity and Data Management', 'category' => 'Digital Skills', 'schedule' => 'September 8-9, 2026', 'slots' => 30, 'provider' => 'CSC ICT Academy', 'mode' => 'Hybrid'],
            ['id' => 3, 'title' => 'Technical Writing and Presentation Skills', 'category' => 'Communication', 'schedule' => 'October 6-7, 2026', 'slots' => 25, 'provider' => 'HRDC and Secretariat', 'mode' => 'Face-to-face'],
            ['id' => 4, 'title' => 'Project Planning and Monitoring Workshop', 'category' => 'Project Management', 'schedule' => 'November 12-14, 2026', 'slots' => 20, 'provider' => 'Accredited Training Partner', 'mode' => 'Hybrid'],
        ];
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
