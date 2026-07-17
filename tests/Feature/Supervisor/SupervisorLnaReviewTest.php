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

test('supervisor sees lna assessments from employees in the same office', function () {
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
            ->has('lnaEntries', 1)
            ->where('lnaEntries.0.employee_id', 'EMP-100')
            ->where('lnaEntries.0.prescriptive_analytics.skills_gap', 'Leadership, coaching, delegation, and team supervision')
        );
});

test('supervisor can review an lna assessment from their office', function () {
    $supervisor = makeLnaUser('supervisor', 'SUP-101', 'Operations');
    $teamMember = makeLnaUser('employee', 'EMP-101', 'Operations');
    $entry = LearningNeedsAnalysis::query()->create([
        'user_id' => $teamMember->id,
        'employee_id' => $teamMember->employee_id,
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
        ])
        ->assertRedirect();

    expect($entry->fresh())
        ->status->toBe('reviewed')
        ->reviewed_by->toBe($supervisor->id)
        ->review_remarks->toBe('Proceed with the recommended workshop.');
});

test('supervisor cannot review an lna assessment outside their office', function () {
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
        ->assertForbidden();

    expect($entry->fresh()->status)->toBe('submitted');
});
