<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\LearningNeedsAnalysis;
use App\Models\User;
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
            ->with(['user.employeeRecord', 'reviewer'])
            ->latest('submitted_on')
            ->latest('id')
            ->get()
            ->filter(fn (LearningNeedsAnalysis $entry) => $this->canReview($supervisor, $entry))
            ->values();

        return Inertia::render('Supervisor/LearningNeedsAnalysis/Index', [
            'teamOffice' => $teamOffice,
            'summary' => $this->summary($entries),
            'lnaEntries' => $entries->map(function (LearningNeedsAnalysis $entry) {
                $analytics = $this->analyticsFor($entry);

                return [
                    'id' => $entry->id,
                    'employee_name' => $entry->user->name,
                    'employee_id' => $entry->employee_id,
                    'office' => $this->effectiveOffice($entry->user),
                    'position' => $entry->user->employeeRecord?->position,
                    'focus_area' => $entry->focus_area,
                    'competency_gap' => $entry->competency_gap,
                    'proposed_intervention' => $entry->proposed_intervention,
                    'priority_level' => $entry->priority_level,
                    'status' => $entry->status,
                    'submitted_on' => $entry->submitted_on?->toDateString(),
                    'review_remarks' => $entry->review_remarks,
                    'reviewed_at' => $entry->reviewed_at?->toDateTimeString(),
                    'reviewed_by' => $entry->reviewer?->name,
                    ...$analytics,
                ];
            }),
        ]);
    }

    public function update(Request $request, LearningNeedsAnalysis $learningNeedsAnalysis): RedirectResponse
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
        ]);

        $learningNeedsAnalysis->update([
            'status' => $validated['status'],
            'review_remarks' => $validated['review_remarks'] ?? null,
            'reviewed_by' => $supervisor->id,
            'reviewed_at' => now(),
        ]);

        $message = $validated['status'] === 'reviewed'
            ? 'The employee LNA assessment has been marked as reviewed.'
            : 'The employee LNA assessment has been returned with remarks.';

        return back()->with('success', $message);
    }

    private function canReview(User $supervisor, LearningNeedsAnalysis $entry): bool
    {
        $employee = $entry->user;

        if (! $employee->hasRole('employee')) {
            return false;
        }

        $supervisorOffice = $this->effectiveOffice($supervisor);
        $employeeOffice = $this->effectiveOffice($employee);

        return $supervisorOffice !== null
            && $employeeOffice !== null
            && strcasecmp($supervisorOffice, $employeeOffice) === 0;
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

    /**
     * @return array<string, array<string, int|string>>
     */
    private function analyticsFor(LearningNeedsAnalysis $entry): array
    {
        $context = strtolower(trim($entry->focus_area.' '.$entry->competency_gap.' '.$entry->proposed_intervention));

        $profile = match (true) {
            str_contains($context, 'lead') || str_contains($context, 'supervis') => [
                'category' => 'Leadership and Supervision',
                'skills_gap' => 'Leadership, coaching, delegation, and team supervision',
                'training' => 'Supervisory Development Program',
                'delivery' => 'In-house workshop with guided coaching',
                'timeframe' => 'Within the next 3 months',
            ],
            str_contains($context, 'data') || str_contains($context, 'excel') || str_contains($context, 'digital') || str_contains($context, 'system') => [
                'category' => 'Digital Productivity and Data Management',
                'skills_gap' => 'Digital tools, records management, and data analysis',
                'training' => 'Digital Productivity and Data Management Workshop',
                'delivery' => 'Hands-on workshop and workplace application',
                'timeframe' => 'Within the next 2 months',
            ],
            str_contains($context, 'commun') || str_contains($context, 'writing') || str_contains($context, 'report') || str_contains($context, 'present') => [
                'category' => 'Communication and Technical Writing',
                'skills_gap' => 'Written communication, presentation, and report preparation',
                'training' => 'Technical Writing and Presentation Skills Training',
                'delivery' => 'Instructor-led training with output review',
                'timeframe' => 'Within the next 3 months',
            ],
            str_contains($context, 'customer') || str_contains($context, 'client') || str_contains($context, 'service') => [
                'category' => 'Customer Service Excellence',
                'skills_gap' => 'Client handling, service delivery, and stakeholder engagement',
                'training' => 'Customer Service Excellence Program',
                'delivery' => 'Scenario-based workshop and coaching',
                'timeframe' => 'Within the next 2 months',
            ],
            str_contains($context, 'plan') || str_contains($context, 'project') || str_contains($context, 'monitor') => [
                'category' => 'Planning and Project Monitoring',
                'skills_gap' => 'Planning, implementation monitoring, and target management',
                'training' => 'Project Planning and Monitoring Workshop',
                'delivery' => 'Workshop with an applied project plan',
                'timeframe' => 'Within the next 3 months',
            ],
            default => [
                'category' => 'Core Functional Capability',
                'skills_gap' => 'Role-specific functional competency requiring development',
                'training' => 'Functional Competency Enhancement Training',
                'delivery' => 'In-house training with supervisor coaching',
                'timeframe' => 'Within the next 6 months',
            ],
        };

        $priorityWeight = match ($entry->priority_level) {
            'high' => 90,
            'medium' => 65,
            default => 40,
        };

        return [
            'descriptive_analytics' => [
                'competency_category' => $profile['category'],
                'priority_score' => $priorityWeight,
                'assessment_finding' => ucfirst($entry->priority_level).' priority gap identified in '.$entry->focus_area.'.',
            ],
            'prescriptive_analytics' => [
                'skills_gap' => $profile['skills_gap'],
                'recommended_action' => $profile['delivery'],
                'target_timeframe' => $profile['timeframe'],
            ],
            'predictive_analytics' => [
                'training_recommendation' => $profile['training'],
                'match_score' => min(96, $priorityWeight + 6),
                'expected_outcome' => 'Improved workplace performance in '.$profile['category'].'.',
            ],
        ];
    }
}
