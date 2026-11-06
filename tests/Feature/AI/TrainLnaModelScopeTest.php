<?php

use App\Models\EmployeeRecord;
use App\Models\LearningNeedsAnalysis;
use App\Models\LnaModelTrainingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createTrainableLna(string $employeeId, string $employmentStatus, bool $completeProfile = true): void
{
    $user = User::factory()->create([
        'employee_id' => $employeeId,
        'office' => 'Operations',
    ]);

    EmployeeRecord::query()->create([
        'employee_id' => $employeeId,
        'first_name' => 'Demo',
        'last_name' => $employeeId,
        'office' => 'Operations',
        'position' => 'Administrative Aide',
        'education_level' => $completeProfile ? "Bachelor's Degree" : null,
        'role_family' => $completeProfile ? 'administration' : null,
        'seniority_level' => $completeProfile ? 1 : null,
        'hire_date' => $completeProfile ? '2020-01-01' : null,
        'employment_status' => $employmentStatus,
    ]);

    LearningNeedsAnalysis::query()->create([
        'user_id' => $user->id,
        'employee_id' => $employeeId,
        'ipcr_rating' => '3.2',
        'skill_assessments' => ['Communication Skills' => '2'],
        'supervisor_skill_assessments' => ['Communication Skills' => '2'],
        'focus_area' => 'Communication Skills',
        'competency_gap' => 'Needs communication practice',
        'proposed_intervention' => 'Communication workshop',
        'priority_level' => 'medium',
        'status' => 'reviewed',
    ]);
}

test('automatic lna training uses complete permanent employee records only', function () {
    createTrainableLna('EMP-SCOPE-001', 'Permanent');
    createTrainableLna('EMP-SCOPE-002', 'Casual');
    createTrainableLna('EMP-SCOPE-003', 'Permanent', completeProfile: false);

    Artisan::call('lna:train', ['--force' => true]);

    expect(LnaModelTrainingRun::query()->latest('id')->value('source_rows'))->toBe(1);
});
