<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningActionPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use Inertia\Inertia;
use Inertia\Response;

class AdminLearningDevelopmentController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'summary' => [
                'total_lna_submissions' => LearningNeedsAnalysis::query()->count(),
                'high_priority_lna' => LearningNeedsAnalysis::query()->where('priority_level', 'high')->count(),
                'training_applications' => TrainingApplication::query()->count(),
                'invitational_trainings' => TrainingApplication::query()->where('training_type', 'Invitational')->count(),
                'in_house_trainings' => TrainingApplication::query()->where('training_type', 'In-house')->count(),
                'trainings_attended' => TrainingApplication::query()->where('is_attended', true)->count(),
                'lap_submissions' => LearningActionPlan::query()->count(),
                'completed_lap' => LearningActionPlan::query()->where('status', 'completed')->count(),
            ],
            'roleBreakdown' => [
                [
                    'role' => 'submitted lna',
                    'count' => LearningNeedsAnalysis::query()->distinct('user_id')->count('user_id'),
                ],
                [
                    'role' => 'applied trainings',
                    'count' => TrainingApplication::query()->distinct('user_id')->count('user_id'),
                ],
                [
                    'role' => 'submitted lap',
                    'count' => LearningActionPlan::query()->distinct('user_id')->count('user_id'),
                ],
            ],
            'officeBreakdown' => TrainingApplication::query()
                ->selectRaw('office, count(*) as total')
                ->groupBy('office')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'office' => $row->office ?: 'Unassigned',
                    'count' => (int) $row->total,
                ]),
            'lnaRecords' => LearningNeedsAnalysis::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (LearningNeedsAnalysis $item) => [
                    'id' => $item->id,
                    'employee_id' => $item->employee_id,
                    'employee_name' => $item->user?->name ?? 'Unknown',
                    'focus_area' => $item->focus_area,
                    'priority_level' => $item->priority_level,
                    'status' => $item->status,
                    'submitted_on' => $item->submitted_on?->toDateString(),
                ]),
            'trainingRecords' => TrainingApplication::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (TrainingApplication $item) => [
                    'id' => $item->id,
                    'employee_id' => $item->employee_id,
                    'employee_name' => $item->user?->name ?? 'Unknown',
                    'training_title' => $item->training_title,
                    'training_type' => $item->training_type,
                    'progress_percent' => $item->progress_percent,
                    'status' => $item->status,
                    'is_attended' => $item->is_attended,
                ]),
            'lapRecords' => LearningActionPlan::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (LearningActionPlan $item) => [
                    'id' => $item->id,
                    'employee_id' => $item->employee_id,
                    'employee_name' => $item->user?->name ?? 'Unknown',
                    'training_title' => $item->training_title,
                    'status' => $item->status,
                    'submitted_on' => $item->submitted_on?->toDateString(),
                ]),
        ]);
    }
}
