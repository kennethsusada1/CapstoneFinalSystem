<?php

use App\Models\EmployeeRecord;
use App\Models\LearningDevelopmentPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\ProposedTrainingProgram;
use App\Models\TrainingApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['employee', 'supervisor', 'secretariat', 'hrdc'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function makeWorkflowUser(string $role, string $employeeId, string $office): User
{
    $user = User::factory()->create([
        'employee_id' => $employeeId,
        'office' => $office,
    ]);
    $user->assignRole($role);

    EmployeeRecord::query()->create([
        'employee_id' => $employeeId,
        'first_name' => ucfirst($role),
        'last_name' => $employeeId,
        'office' => $office,
        'position' => match ($role) {
            'supervisor' => 'Division Chief',
            'secretariat' => 'L&D Secretariat',
            'hrdc' => 'HRDC Member',
            default => 'Employee',
        },
    ]);

    return $user;
}

test('training application requires a supervisor reviewed lna', function () {
    $employee = makeWorkflowUser('employee', 'EMP-301', 'Operations');
    $lna = LearningNeedsAnalysis::query()->create([
        'user_id' => $employee->id,
        'employee_id' => $employee->employee_id,
        'focus_area' => 'Technical writing',
        'competency_gap' => 'Needs stronger report writing',
        'proposed_intervention' => 'Technical writing workshop',
        'priority_level' => 'high',
        'status' => 'submitted',
        'submitted_on' => now()->toDateString(),
    ]);

    $this->actingAs($employee)
        ->post('/employee/training-applications', [
            'lna_id' => $lna->id,
            'training_type' => 'In-house',
        ])
        ->assertSessionHasErrors('lna_id');

    expect(TrainingApplication::query()->count())->toBe(0);
});

test('secretariat is notified after supervisor evaluates an employee lna', function () {
    $employee = makeWorkflowUser('employee', 'EMP-303', 'Operations');
    $supervisor = makeWorkflowUser('supervisor', 'SUP-303', 'Operations');
    $secretariat = makeWorkflowUser('secretariat', 'SEC-303', 'Human Resources');
    $lna = LearningNeedsAnalysis::query()->create([
        'user_id' => $employee->id,
        'employee_id' => $employee->employee_id,
        'focus_area' => 'Data analysis',
        'competency_gap' => 'Needs stronger spreadsheet analysis',
        'proposed_intervention' => 'Data management workshop',
        'priority_level' => 'high',
        'status' => 'submitted',
        'submitted_on' => now()->toDateString(),
    ]);

    $this->actingAs($supervisor)
        ->patch("/supervisor/lna-reviews/{$lna->id}", [
            'status' => 'reviewed',
            'review_remarks' => 'Endorsed for Secretariat training coordination.',
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonFragment([
            'event' => 'lna.reviewed_by_supervisor',
            'title' => 'Employee LNA Evaluated',
            'url' => '/secretariat/applications',
        ]);
});

test('employee request flows from supervisor to secretariat and hrdc approval', function () {
    $employee = makeWorkflowUser('employee', 'EMP-302', 'Operations');
    $supervisor = makeWorkflowUser('supervisor', 'SUP-302', 'Operations');
    $secretariat = makeWorkflowUser('secretariat', 'SEC-302', 'Human Resources');
    $hrdc = makeWorkflowUser('hrdc', 'HRDC-302', 'Human Resources');

    $this->actingAs($employee)
        ->post('/employee/learning-needs-analysis', [
            'focus_area' => 'Technical writing',
            'competency_gap' => 'Needs stronger report writing',
            'proposed_intervention' => 'Technical writing workshop',
            'priority_level' => 'high',
        ])
        ->assertRedirect();

    $lna = LearningNeedsAnalysis::query()->sole();

    $this->actingAs($supervisor)
        ->patch("/supervisor/lna-reviews/{$lna->id}", [
            'status' => 'reviewed',
            'review_remarks' => 'Endorsed for the recommended technical writing program.',
        ])
        ->assertRedirect();

    $this->actingAs($employee)
        ->post('/employee/training-applications', [
            'lna_id' => $lna->id,
            'training_type' => 'In-house',
            'provider' => 'HRDC Learning Unit',
        ])
        ->assertRedirect();

    $application = TrainingApplication::query()->sole();

    expect($application)
        ->learning_needs_analysis_id->toBe($lna->id)
        ->secretariat_status->toBe('pending')
        ->status->toBe('applied');

    $this->actingAs($secretariat)
        ->patch("/secretariat/applications/{$application->id}", [
            'secretariat_status' => 'processed',
            'process_remarks' => 'Documents verified for L&D planning.',
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->post('/secretariat/ld-plans', [
            'training_application_id' => $application->id,
            'title' => 'L&D Plan - Technical Writing',
            'planning_year' => '2026',
            'objectives' => 'Improve the employee technical report writing competency.',
            'priority_programs' => $application->training_title,
            'budget_notes' => 'Subject to available L&D funds.',
            'status' => 'submitted',
        ])
        ->assertRedirect();

    $plan = LearningDevelopmentPlan::query()->sole();
    $program = ProposedTrainingProgram::query()->sole();

    expect($plan->training_application_id)->toBe($application->id);
    expect($program->title)->toBe($application->training_title);

    $this->actingAs($hrdc)
        ->patch("/hrdc/program-approvals/{$program->id}", [
            'status' => 'approved',
            'review_remarks' => 'Approved based on the endorsed LNA and submitted L&D Plan.',
        ])
        ->assertRedirect();

    expect($program->fresh()->status)->toBe('approved');
    expect($plan->fresh()->review_status)->toBe('approved');
    expect($application->fresh()->status)->toBe('ongoing');

    $this->actingAs($employee)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonFragment([
            'title' => 'You Will Undergo Training',
            'url' => "/employee/training-applications/{$application->id}",
        ]);
});

test('employee is notified when hrdc disapproves the proposed training program', function () {
    $employee = makeWorkflowUser('employee', 'EMP-304', 'Operations');
    $supervisor = makeWorkflowUser('supervisor', 'SUP-304', 'Operations');
    $secretariat = makeWorkflowUser('secretariat', 'SEC-304', 'Human Resources');
    $hrdc = makeWorkflowUser('hrdc', 'HRDC-304', 'Human Resources');
    $lna = LearningNeedsAnalysis::query()->create([
        'user_id' => $employee->id,
        'employee_id' => $employee->employee_id,
        'focus_area' => 'Project monitoring',
        'competency_gap' => 'Needs stronger monitoring capability',
        'proposed_intervention' => 'Project monitoring workshop',
        'priority_level' => 'medium',
        'status' => 'reviewed',
        'review_remarks' => 'Endorsed for HRDC consideration.',
        'reviewed_by' => $supervisor->id,
        'reviewed_at' => now(),
        'submitted_on' => now()->toDateString(),
    ]);
    $application = TrainingApplication::query()->create([
        'user_id' => $employee->id,
        'learning_needs_analysis_id' => $lna->id,
        'employee_id' => $employee->employee_id,
        'training_title' => 'Project Planning and Monitoring Workshop',
        'training_type' => 'Invitational',
        'office' => $employee->office,
        'status' => 'applied',
        'secretariat_status' => 'processed',
        'processed_by' => $secretariat->id,
        'processed_at' => now(),
    ]);
    $plan = LearningDevelopmentPlan::query()->create([
        'submitted_by' => $secretariat->id,
        'training_application_id' => $application->id,
        'title' => 'L&D Plan - Project Monitoring',
        'planning_year' => '2026',
        'objectives' => 'Improve project monitoring competency.',
        'priority_programs' => $application->training_title,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    $program = ProposedTrainingProgram::query()->create([
        'learning_development_plan_id' => $plan->id,
        'title' => $application->training_title,
        'status' => 'pending',
    ]);

    $this->actingAs($hrdc)
        ->patch("/hrdc/program-approvals/{$program->id}", [
            'status' => 'disapproved',
            'review_remarks' => 'The proposal requires a revised budget and implementation schedule.',
        ])
        ->assertRedirect();

    expect($application->fresh()->status)->toBe('rejected');

    $this->actingAs($employee)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonFragment([
            'title' => 'Training Program Disapproved',
            'body' => 'The proposal requires a revised budget and implementation schedule.',
            'url' => "/employee/training-applications/{$application->id}",
        ]);
});
