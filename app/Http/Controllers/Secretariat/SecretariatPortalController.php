<?php

namespace App\Http\Controllers\Secretariat;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningDevelopmentPlan;
use App\Models\ProposedTrainingProgram;
use App\Models\TrainingApplication;
use App\Models\User;
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
                ['label' => 'Pending requests', 'value' => $applications->where('status', 'applied')->count(), 'color' => '#facc15'],
                ['label' => 'Approved / ongoing', 'value' => $applications->where('status', 'ongoing')->count(), 'color' => '#38bdf8'],
                ['label' => 'Completed', 'value' => $applications->where('status', 'completed')->count(), 'color' => '#34d399'],
            ],
        ]);
    }

    public function applications(): Response
    {
        $applications = TrainingApplication::query()->with('user')->latest()->get();

        return Inertia::render('Secretariat/Applications/Index', [
            'applications' => $this->applicationRows($applications),
        ]);
    }

    public function processApplication(Request $request, TrainingApplication $trainingApplication): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['applied', 'ongoing', 'completed', 'rejected'])],
            'process_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $trainingApplication->update([
            'status' => $validated['status'],
            'process_remarks' => $validated['process_remarks'] ?? null,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'completed_on' => $validated['status'] === 'completed' ? ($trainingApplication->completed_on ?? now()->toDateString()) : null,
        ]);

        return back()->with('success', 'Training application status updated successfully.');
    }

    public function ldPlans(): Response
    {
        return Inertia::render('Secretariat/LearningDevelopmentPlans/Index', [
            'plans' => LearningDevelopmentPlan::query()->with('submitter')->latest()->get()->map(fn (LearningDevelopmentPlan $plan) => $this->ldPlanRow($plan)),
            'programs' => $this->programCatalog(),
            'currentYear' => now()->year,
        ]);
    }

    public function storeLdPlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'planning_year' => ['required', 'digits:4'],
            'objectives' => ['required', 'string', 'max:5000'],
            'priority_programs' => ['required', 'string', 'max:5000'],
            'budget_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['draft', 'submitted'])],
        ]);

        $plan = LearningDevelopmentPlan::query()->create([
            ...$validated,
            'submitted_by' => $request->user()->id,
            'submitted_at' => $validated['status'] === 'submitted' ? now() : null,
        ]);

        collect(preg_split('/\r\n|\r|\n|;/', $validated['priority_programs']) ?: [])
            ->map(fn (string $title) => trim($title))
            ->filter()
            ->unique()
            ->each(fn (string $title) => ProposedTrainingProgram::query()->create([
                'learning_development_plan_id' => $plan->id,
                'title' => $title,
                'status' => 'pending',
            ]));

        return back()->with('success', 'Learning and Development Plan saved successfully.');
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
        $applications = TrainingApplication::query()->with('user')->latest()->get();
        $plans = LearningActionPlan::query()->with('user')->latest()->get();

        return Inertia::render('Secretariat/Reports/Index', [
            'summary' => $this->reportSummary($applications, $plans),
            'activities' => $applications->groupBy('training_title')->map(fn ($items, $title) => [
                'training_title' => $title,
                'participants' => $items->count(),
                'approved' => $items->whereIn('status', ['ongoing', 'completed'])->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'average_progress' => (int) round($items->avg('progress_percent') ?? 0),
            ])->values(),
            'offices' => $applications->groupBy(fn (TrainingApplication $item) => $item->office ?: $item->user->office ?: $item->user->employeeRecord?->office ?: 'Unassigned')
                ->map(fn ($items, $office) => ['office' => $office, 'applications' => $items->count(), 'completed' => $items->where('status', 'completed')->count()])
                ->sortByDesc('applications')
                ->values(),
        ]);
    }

    public function exportReport(): HttpResponse
    {
        $applications = TrainingApplication::query()->with('user')->latest()->get();
        $approved = $applications->whereIn('status', ['ongoing', 'completed']);
        $completed = $applications->where('status', 'completed')->count();
        $completionRate = $approved->isEmpty() ? 0 : (int) round(($completed / $approved->count()) * 100);
        $lines = [
            'SMART L&D',
            'TERMINAL TRAINING ACTIVITY REPORT',
            'Generated: '.now()->format('F d, Y h:i A'),
            '',
            'SUMMARY',
            'Total applications: '.$applications->count(),
            'Approved activities: '.$approved->count(),
            'Completed activities: '.$completed,
            'Completion rate: '.$completionRate.'%',
            '',
            'TRAINING ACTIVITY DETAILS',
            'Employee | Employee ID | Office | Training | Status | Progress | Schedule',
        ];

        foreach ($applications as $application) {
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
            'pending_requests' => $applications->where('status', 'applied')->count(),
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
            'is_attended' => $application->is_attended,
            'process_remarks' => $application->process_remarks,
            'processed_at' => $application->processed_at?->toDateTimeString(),
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

        return [
            'total_applications' => $applications->count(),
            'approved_activities' => $approved->count(),
            'completed_activities' => $applications->where('status', 'completed')->count(),
            'lap_submissions' => $plans->count(),
            'lap_received' => $plans->where('receipt_status', 'received')->count(),
            'completion_rate' => $approved->isEmpty() ? 0 : (int) round(($applications->where('status', 'completed')->count() / $approved->count()) * 100),
        ];
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
}
