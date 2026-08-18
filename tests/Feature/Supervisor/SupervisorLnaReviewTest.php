<?php

use App\Models\EmployeeRecord;
use App\Models\LearningNeedsAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['supervisor', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function makeLnaUser(string $role, string $employeeId, string $office): User
{
    $user = User::factory()->create([
        'employee_id' => $employeeId,
        'office' => $office,
    ]);
    $user->assignRole($role);

    EmployeeRecord::query()->create([
        'employee_id' => $employeeId,
        'first_name' => $role,
        'last_name' => $employeeId,
        'office' => $office,
        'position' => $role === 'supervisor' ? 'Division Chief' : 'Staff',
    ]);

    return $user;
}

test('supervisor sees lna assessments from every employee office', function () {
    $supervisor = makeLnaUser('supervisor', 'SUP-100', 'Operations');
    $teamMember = makeLnaUser('employee', 'EMP-100', 'Operations');
    $otherEmployee = makeLnaUser('employee', 'EMP-200', 'Finance');

    LearningNeedsAnalysis::query()->create([
        'user_id' => $teamMember->id,
        'employee_id' => $teamMember->employee_id,
        'focus_area' => 'Leadership',
        'competency_gap' => 'Needs coaching practice',
        'proposed_intervention' => 'Leadership training',
        'priority_level' => 'high',
        'status' => 'submitted',
    ]);

    LearningNeedsAnalysis::query()->create([
        'user_id' => $otherEmployee->id,
        'employee_id' => $otherEmployee->employee_id,
        'focus_area' => 'Data analysis',
        'competency_gap' => 'Needs spreadsheet practice',
        'proposed_intervention' => 'Excel workshop',
        'priority_level' => 'medium',
        'status' => 'submitted',
    ]);

    $this->actingAs($supervisor)
        ->get('/supervisor/lna-reviews')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Supervisor/LearningNeedsAnalysis/Index')
            ->has('lnaEntries', 2)
            ->where('lnaEntries.0.employee_id', 'EMP-200')
            ->where('lnaEntries.1.employee_id', 'EMP-100')
        );
});

test('the only supervisor receives an lna from an office without an assigned supervisor', function () {
    $supervisor = makeLnaUser('supervisor', 'SUP-099', 'Operations');
    $employee = makeLnaUser('employee', 'EMP-099', 'HRMO');

    $entry = LearningNeedsAnalysis::query()->create([
        'user_id' => $employee->id,
        'employee_id' => $employee->employee_id,
        'focus_area' => 'Communication',
        'competency_gap' => 'Needs stronger written communication',
        'proposed_intervention' => 'Technical writing workshop',
        'priority_level' => 'medium',
        'status' => 'submitted',
    ]);

    $this->actingAs($supervisor)
        ->get('/supervisor/lna-reviews')
        ->assertInertia(fn ($page) => $page
            ->has('lnaEntries', 1)
            ->where('lnaEntries.0.id', $entry->id));
});

test('supervisor can review an lna assessment from their office', function () {
    $supervisor = makeLnaUser('supervisor', 'SUP-101', 'Operations');
    $teamMember = makeLnaUser('employee', 'EMP-101', 'Operations');
    $entry = LearningNeedsAnalysis::query()->create([
        'user_id' => $teamMember->id,
        'employee_id' => $teamMember->employee_id,
        'skill_assessments' => [
            'Communication Skills' => '2',
            'Technical Writing' => '3',
        ],
        'focus_area' => 'Technical writing',
        'competency_gap' => 'Report writing',
        'proposed_intervention' => 'Writing workshop',
        'priority_level' => 'high',
        'status' => 'submitted',
    ]);

    $this->actingAs($supervisor)
        ->patch("/supervisor/lna-reviews/{$entry->id}", [
            'status' => 'reviewed',
            'review_remarks' => 'Proceed with the recommended workshop.',
            'supervisor_skill_assessments' => [
                'Communication Skills' => '3',
                'Technical Writing' => '2',
            ],
            'supervisor_assessment_methods' => [
                'Supervisor Assessment',
                'Observation',
                'Performance Review',
            ],
            'supervisor_signature' => $supervisor->name,
            'supervisor_signed_on' => '2026-08-13',
        ])
        ->assertRedirect();

    expect($entry->fresh())
        ->status->toBe('reviewed')
        ->reviewed_by->toBe($supervisor->id)
        ->review_remarks->toBe('Proceed with the recommended workshop.')
        ->skill_assessments->toBe([
            'Communication Skills' => '2',
            'Technical Writing' => '3',
        ])
        ->supervisor_skill_assessments->toBe([
            'Communication Skills' => '3',
            'Technical Writing' => '2',
        ])
        ->supervisor_assessment_methods->toBe([
            'Supervisor Assessment',
            'Observation',
            'Performance Review',
        ])
        ->supervisor_signature->toBe($supervisor->name)
        ->supervisor_signed_on->toDateString()->toBe('2026-08-13')
        ->predictive_skills_gap->toBe('Communication Skills, Technical Writing')
        ->prescriptive_training_recommendation->toBe('Technical Writing and Presentation Skills Training')
        ->analytics_generated_at->not->toBeNull();
});

test('supervisor can review an lna assessment outside their office', function () {
    $supervisor = makeLnaUser('supervisor', 'SUP-102', 'Operations');
    $otherEmployee = makeLnaUser('employee', 'EMP-202', 'Finance');
    $entry = LearningNeedsAnalysis::query()->create([
        'user_id' => $otherEmployee->id,
        'employee_id' => $otherEmployee->employee_id,
        'focus_area' => 'Data analysis',
        'competency_gap' => 'Spreadsheet formulas',
        'proposed_intervention' => 'Excel workshop',
        'priority_level' => 'medium',
        'status' => 'submitted',
    ]);

    $this->actingAs($supervisor)
        ->patch("/supervisor/lna-reviews/{$entry->id}", [
            'status' => 'reviewed',
        ])
        ->assertRedirect();

    expect($entry->fresh()->status)->toBe('reviewed');
});
