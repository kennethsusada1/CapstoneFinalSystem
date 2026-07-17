<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorPortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $supervisor = $this->supervisor($request);
        $team = $this->team($supervisor);
        $teamIds = $team->modelKeys();
        $lnaEntries = LearningNeedsAnalysis::query()->whereIn('user_id', $teamIds)->with('user')->latest()->get();
        $trainings = TrainingApplication::query()->whereIn('user_id', $teamIds)->with('user')->latest()->get();
        $lapEntries = LearningActionPlan::query()->whereIn('user_id', $teamIds)->with('user')->latest()->get();

        $pendingLna = $lnaEntries->where('status', 'submitted');
        $activeTrainings = $trainings->where('status', 'ongoing');

        $lnaAttention = $pendingLna->take(4)->map(fn (LearningNeedsAnalysis $entry) => [
            'type' => 'LNA Review',
            'title' => $entry->user->name,
            'detail' => $entry->focus_area.' requires supervisor review.',
            'href' => '/supervisor/lna-reviews',
            'priority' => $entry->priority_level,
        ]);
        $lapAttention = $trainings
            ->filter(fn (TrainingApplication $training) => ($training->status === 'completed' || $training->is_attended)
                && ! $lapEntries->contains(fn (LearningActionPlan $lap) => $lap->user_id === $training->user_id && $lap->training_title === $training->training_title))
            ->take(3)
            ->map(fn (TrainingApplication $training) => [
                'type' => 'LAP Follow-up',
                'title' => $training->user->name,
                'detail' => $training->training_title.' is awaiting a Learning Action Plan.',
                'href' => '/supervisor/idp',
                'priority' => 'medium',
            ]);

        return Inertia::render('Supervisor/Dashboard', [
            'supervisor' => $this->profileData($supervisor),
            'stats' => [
                'team_members' => $team->count(),
                'pending_lna' => $pendingLna->count(),
                'active_trainings' => $activeTrainings->count(),
                'submitted_lap' => $lapEntries->whereIn('status', ['submitted', 'completed'])->count(),
            ],
            'teamProgress' => $team->map(function (User $employee) use ($lnaEntries, $trainings, $lapEntries) {
                $employeeTrainings = $trainings->where('user_id', $employee->id);
                $completed = $employeeTrainings->where('status', 'completed')->count();
                $total = $employeeTrainings->count();

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $employee->employeeRecord?->position,
                    'lna_count' => $lnaEntries->where('user_id', $employee->id)->count(),
                    'training_progress' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
                    'lap_count' => $lapEntries->where('user_id', $employee->id)->count(),
                ];
            })->values(),
            'attentionItems' => $lnaAttention->merge($lapAttention)->values(),
            'trainingMix' => [
                ['label' => 'Applied', 'value' => $trainings->where('status', 'applied')->count(), 'color' => '#38bdf8'],
                ['label' => 'Ongoing', 'value' => $activeTrainings->count(), 'color' => '#f59e0b'],
                ['label' => 'Completed', 'value' => $trainings->where('status', 'completed')->count(), 'color' => '#34d399'],
            ],
            'upcomingPrograms' => array_slice($this->trainingCatalog(), 0, 3),
        ]);
    }

    public function teamIndex(Request $request): Response
    {
        $supervisor = $this->supervisor($request);
        $team = $this->team($supervisor);
        $teamIds = $team->modelKeys();
        $lnaEntries = LearningNeedsAnalysis::query()->whereIn('user_id', $teamIds)->get();
        $trainings = TrainingApplication::query()->whereIn('user_id', $teamIds)->latest()->get();
        $lapEntries = LearningActionPlan::query()->whereIn('user_id', $teamIds)->get();

        return Inertia::render('Supervisor/Team/Index', [
            'office' => $this->office($supervisor),
            'members' => $team->map(function (User $employee) use ($lnaEntries, $trainings, $lapEntries) {
                $employeeLna = $lnaEntries->where('user_id', $employee->id);
                $employeeTrainings = $trainings->where('user_id', $employee->id);
                $activeTraining = $employeeTrainings->firstWhere('status', 'ongoing');
                $completed = $employeeTrainings->where('status', 'completed')->count();
                $readiness = min(100, 35 + ($employeeLna->count() * 15) + ($completed * 20) + ($lapEntries->where('user_id', $employee->id)->count() * 10));
                $lnaStatus = $employeeLna->isEmpty()
                    ? 'not started'
                    : $employeeLna->sortByDesc('id')->first()->status;

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_id' => $employee->employee_id,
                    'email' => $employee->email,
                    'position' => $employee->employeeRecord?->position,
                    'office' => $this->office($employee),
                    'lna_status' => $lnaStatus,
                    'lna_count' => $employeeLna->count(),
                    'active_training' => $activeTraining?->training_title,
                    'completed_trainings' => $completed,
                    'lap_count' => $lapEntries->where('user_id', $employee->id)->count(),
                    'development_readiness' => $readiness,
                ];
            })->values(),
        ]);
    }

    public function nominations(Request $request): Response
    {
        $supervisor = $this->supervisor($request);

        return Inertia::render('Supervisor/Nominations/Index', [
            'nominations' => $this->nominationRecords($this->team($supervisor)),
            'programs' => $this->trainingCatalog(),
        ]);
    }

    public function nominationShow(Request $request, int $id): Response
    {
        $supervisor = $this->supervisor($request);
        $nomination = collect($this->nominationRecords($this->team($supervisor)))->firstWhere('id', $id);

        abort_unless($nomination !== null, 404);

        return Inertia::render('Supervisor/Nominations/Show', [
            'nomination' => $nomination,
        ]);
    }

    public function trainings(Request $request): Response
    {
        $supervisor = $this->supervisor($request);
        $team = $this->team($supervisor);

        return Inertia::render('Supervisor/Trainings/Index', [
            'trainingApplications' => TrainingApplication::query()
                ->whereIn('user_id', $team->modelKeys())
                ->with('user')
                ->latest()
                ->get()
                ->map(fn (TrainingApplication $training) => [
                    'id' => $training->id,
                    'employee_name' => $training->user->name,
                    'employee_id' => $training->employee_id,
                    'training_title' => $training->training_title,
                    'training_type' => $training->training_type,
                    'provider' => $training->provider,
                    'start_date' => $training->start_date?->toDateString(),
                    'end_date' => $training->end_date?->toDateString(),
                    'progress_percent' => $training->progress_percent,
                    'status' => $training->status,
                    'is_attended' => $training->is_attended,
                ])->values(),
            'programs' => $this->trainingCatalog(),
        ]);
    }

    public function idpIndex(Request $request): Response
    {
        $supervisor = $this->supervisor($request);
        $team = $this->team($supervisor);

        return Inertia::render('Supervisor/IDP/Index', [
            'plans' => LearningActionPlan::query()
                ->whereIn('user_id', $team->modelKeys())
                ->with('user.employeeRecord')
                ->latest()
                ->get()
                ->map(fn (LearningActionPlan $plan) => [
                    'id' => $plan->id,
                    'employee_name' => $plan->user->name,
                    'employee_id' => $plan->employee_id,
                    'position' => $plan->user->employeeRecord?->position,
                    'training_title' => $plan->training_title,
                    'implementation_summary' => $plan->implementation_summary,
                    'learning_outcomes' => $plan->learning_outcomes,
                    'status' => $plan->status,
                    'submitted_on' => $plan->submitted_on?->toDateString(),
                ])->values(),
        ]);
    }

    public function idpShow(Request $request, LearningActionPlan $learningActionPlan): Response
    {
        $supervisor = $this->supervisor($request);
        abort_unless($this->team($supervisor)->contains('id', $learningActionPlan->user_id), 403);
        $learningActionPlan->loadMissing('user.employeeRecord');

        return Inertia::render('Supervisor/IDP/Show', [
            'plan' => [
                'id' => $learningActionPlan->id,
                'employee_name' => $learningActionPlan->user->name,
                'employee_id' => $learningActionPlan->employee_id,
                'position' => $learningActionPlan->user->employeeRecord?->position,
                'office' => $this->office($learningActionPlan->user),
                'training_title' => $learningActionPlan->training_title,
                'implementation_summary' => $learningActionPlan->implementation_summary,
                'learning_outcomes' => $learningActionPlan->learning_outcomes,
                'status' => $learningActionPlan->status,
                'submitted_on' => $learningActionPlan->submitted_on?->toDateString(),
                'milestones' => [
                    ['label' => 'Discuss application plan with supervisor', 'status' => 'completed'],
                    ['label' => 'Apply learning to one workplace output', 'status' => $learningActionPlan->status === 'draft' ? 'pending' : 'completed'],
                    ['label' => 'Share results during team learning session', 'status' => $learningActionPlan->status === 'completed' ? 'completed' : 'pending'],
                ],
            ],
        ]);
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('Supervisor/Profile/Index', [
            'profile' => $this->profileData($this->supervisor($request)),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $supervisor = $this->supervisor($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($supervisor->id)],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $supervisor->update($validated);

        return back()->with('success', 'Supervisor profile updated successfully.');
    }

    private function supervisor(Request $request): User
    {
        return User::query()
            ->with('employeeRecord')
            ->whereKey($request->user()->getAuthIdentifier())
            ->firstOrFail();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function team(User $supervisor): EloquentCollection
    {
        $office = $this->office($supervisor);

        if (! $office) {
            return new EloquentCollection;
        }

        return User::role('employee')
            ->with('employeeRecord')
            ->get()
            ->filter(fn (User $employee) => strcasecmp((string) $this->office($employee), $office) === 0)
            ->values();
    }

    private function office(User $user): ?string
    {
        $office = trim((string) ($user->office ?: $user->employeeRecord?->office));

        return $office !== '' ? $office : null;
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
            'office' => $this->office($user),
            'position' => $user->employeeRecord?->position,
            'address' => $user->address,
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function trainingCatalog(): array
    {
        return [
            ['id' => 1, 'title' => 'Supervisory Development Program', 'category' => 'Leadership', 'schedule' => 'August 18-20, 2026', 'slots' => 24, 'provider' => 'HRDC Learning Unit', 'mode' => 'Face-to-face'],
            ['id' => 2, 'title' => 'Digital Productivity and Data Management', 'category' => 'Digital Skills', 'schedule' => 'September 8-9, 2026', 'slots' => 30, 'provider' => 'CSC ICT Academy', 'mode' => 'Hybrid'],
            ['id' => 3, 'title' => 'Technical Writing and Presentation Skills', 'category' => 'Communication', 'schedule' => 'October 6-7, 2026', 'slots' => 25, 'provider' => 'HRDC and Secretariat', 'mode' => 'Face-to-face'],
            ['id' => 4, 'title' => 'Project Planning and Monitoring Workshop', 'category' => 'Project Management', 'schedule' => 'November 12-14, 2026', 'slots' => 20, 'provider' => 'Accredited Training Partner', 'mode' => 'Hybrid'],
        ];
    }

    /**
     * @param  EloquentCollection<int, User>  $team
     * @return list<array<string, int|string>>
     */
    private function nominationRecords(EloquentCollection $team): array
    {
        $programs = $this->trainingCatalog();

        $records = collect($programs)->map(function (array $program, int $index) use ($team) {
            $employeeName = 'Team member to be assigned';
            $employeeId = 'TBA';
            $position = 'Team position';

            if ($team->isNotEmpty()) {
                $employee = $team->get($index % $team->count());
                $employeeName = $employee->name;
                $employeeId = $employee->employee_id ?? 'TBA';
                $position = $employee->employeeRecord()->value('position') ?: 'Team position';
            }

            return [
                'id' => $program['id'],
                'employee_name' => $employeeName,
                'employee_id' => $employeeId,
                'position' => $position,
                'training_title' => $program['title'],
                'category' => $program['category'],
                'schedule' => $program['schedule'],
                'provider' => $program['provider'],
                'mode' => $program['mode'],
                'status' => ['draft', 'for endorsement', 'endorsed', 'for endorsement'][$index],
                'justification' => 'The nomination supports the employee development priorities identified through team capability review and current work assignments.',
                'expected_output' => 'Apply the acquired competency to a workplace improvement output and share learning with the team.',
            ];
        })->values()->all();

        return array_values($records);
    }
}
