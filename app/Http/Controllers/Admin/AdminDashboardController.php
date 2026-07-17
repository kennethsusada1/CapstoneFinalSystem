<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRecord;
use App\Models\LearningActionPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use App\Models\User;
use Carbon\CarbonPeriod;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $startDate = now()->subDays(6)->startOfDay();
        $period = CarbonPeriod::create($startDate, '1 day', now()->startOfDay());

        $employeeImports = EmployeeRecord::query()
            ->where('created_at', '>=', $startDate)
            ->get()
            ->groupBy(fn (EmployeeRecord $record) => $record->created_at?->format('Y-m-d'));

        $accountCreations = User::query()
            ->where('created_at', '>=', $startDate)
            ->get()
            ->groupBy(fn (User $user) => $user->created_at?->format('Y-m-d'));

        $trainingActivity = TrainingApplication::query()
            ->where('created_at', '>=', $startDate)
            ->get()
            ->groupBy(fn (TrainingApplication $item) => $item->created_at?->format('Y-m-d'));

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'employeeRecords' => EmployeeRecord::query()->count(),
                'userAccounts' => User::query()->count(),
                'activationPending' => User::query()->whereNotNull('employee_id')->whereNull('email_verified_at')->count(),
                'lnaSubmissions' => LearningNeedsAnalysis::query()->count(),
                'trainingAttended' => TrainingApplication::query()->where('is_attended', true)->count(),
                'lapCompleted' => LearningActionPlan::query()->where('status', 'completed')->count(),
            ],
            'charts' => [
                'activityTrend' => collect($period)->map(fn ($date) => [
                    'label' => $date->format('M d'),
                    'employees' => $employeeImports->get($date->format('Y-m-d'))?->count() ?? 0,
                    'accounts' => $accountCreations->get($date->format('Y-m-d'))?->count() ?? 0,
                    'trainings' => $trainingActivity->get($date->format('Y-m-d'))?->count() ?? 0,
                ])->values(),
                'accountStatus' => [
                    ['label' => 'Activated', 'value' => User::query()->whereNotNull('email_verified_at')->count(), 'color' => '#34d399'],
                    ['label' => 'Pending', 'value' => User::query()->whereNull('email_verified_at')->count(), 'color' => '#facc15'],
                ],
                'trainingStatus' => [
                    ['label' => 'Applied', 'value' => TrainingApplication::query()->where('status', 'applied')->count(), 'color' => '#60a5fa'],
                    ['label' => 'Ongoing', 'value' => TrainingApplication::query()->where('status', 'ongoing')->count(), 'color' => '#f59e0b'],
                    ['label' => 'Completed', 'value' => TrainingApplication::query()->where('status', 'completed')->count(), 'color' => '#34d399'],
                ],
                'officeDistribution' => EmployeeRecord::query()
                    ->selectRaw('office, count(*) as total')
                    ->groupBy('office')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get()
                    ->map(fn ($row) => [
                        'label' => $row->office ?: 'Unassigned',
                        'value' => (int) $row->total,
                    ])->values(),
                'roleDistribution' => collect(['system-admin', 'secretariat', 'hrdc', 'supervisor', 'employee'])->map(fn ($role) => [
                    'label' => $role,
                    'value' => User::role($role)->count(),
                ])->values(),
                'learningOverview' => [
                    ['label' => 'LNA', 'value' => LearningNeedsAnalysis::query()->count()],
                    ['label' => 'Training Apps', 'value' => TrainingApplication::query()->count()],
                    ['label' => 'LAP', 'value' => LearningActionPlan::query()->count()],
                ],
            ],
        ]);
    }
}
