<?php

namespace Database\Seeders;

use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $demoMode = filter_var(env('LNA_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN);

        if (! app()->environment(['local', 'testing']) && ! $demoMode) {
            return;
        }

        $employee = User::query()->where('email', 'employee@smartld.test')->first();

        if ($employee === null) {
            return;
        }

        LearningNeedsAnalysis::query()->firstOrCreate(
            [
                'user_id' => $employee->id,
                'focus_area' => 'Demo: Technical Writing and Communication',
            ],
            [
                'employee_id' => $employee->employee_id,
                'ipcr_rating' => '3.25',
                'core_functions' => [
                    'Prepare office correspondence and reports',
                    'Maintain accurate administrative records',
                    'Coordinate requests across work units',
                    'Document service transactions',
                    'Support implementation of office activities',
                    'Prepare clear updates for supervisors',
                ],
                'support_functions' => [
                    'Organize reference materials',
                    'Use digital tools for routine work',
                    'Assist colleagues with information requests',
                    'Follow records management procedures',
                ],
                'skill_assessments' => [
                    'Technical Writing' => '2',
                    'Communication Skills' => '2',
                    'Presentation Skills' => '2',
                    'Negotiation Skills' => '2',
                    'Teamwork' => '3',
                ],
                'preferred_learning_methods' => [
                    'Workshops/Seminars/Trainings',
                    'Mentorship/Coaching',
                ],
                'assessment_methods' => ['Employee Self-Assessment'],
                'employee_signature' => $employee->name,
                'competency_gap' => 'Demo assessment: strengthen technical writing, communication, presentation, and negotiation skills.',
                'proposed_intervention' => 'Technical writing workshop with guided coaching and workplace application.',
                'priority_level' => 'high',
                'status' => 'submitted',
                'submitted_on' => now()->toDateString(),
            ],
        );

        $secretariatId = User::query()
            ->where('email', 'secretariat@smartld.test')
            ->value('id');
        $programStart = now()->addMonths(2)->startOfMonth()->addDays(10);

        foreach ([
            [
                'title' => 'Technical Writing and Presentation Skills Training',
                'category' => 'Communication and Technical Writing',
                'training_type' => 'In-house',
                'delivery_mode' => 'Face-to-face',
                'venue' => 'HRDC Training Room',
            ],
            [
                'title' => 'Communication and Negotiation Workshop',
                'category' => 'Communication and Negotiation',
                'training_type' => 'In-house',
                'delivery_mode' => 'Hybrid',
                'venue' => 'Municipal Training Hall',
            ],
            [
                'title' => 'Teamwork and Collaboration Workshop',
                'category' => 'Interpersonal Skills',
                'training_type' => 'Invitational',
                'delivery_mode' => 'Online',
                'venue' => null,
            ],
        ] as $program) {
            TrainingProgram::query()->firstOrCreate(
                ['title' => $program['title']],
                [
                    ...$program,
                    'description' => 'Synthetic sample program for the Smart L&D demo workflow.',
                    'provider' => 'HRDC Learning and Development Unit',
                    'start_date' => $programStart->toDateString(),
                    'end_date' => $programStart->copy()->addDay()->toDateString(),
                    'slots' => 30,
                    'status' => 'published',
                    'created_by' => $secretariatId,
                ],
            );
        }
    }
}
