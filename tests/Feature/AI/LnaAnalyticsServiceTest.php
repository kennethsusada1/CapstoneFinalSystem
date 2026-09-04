<?php

use App\Models\EmployeeRecord;
use App\Models\LearningNeedsAnalysis;
use App\Models\User;
use App\Services\LnaAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('employee', 'web');
});

test('the exported logistic model generates ranked lna recommendations', function () {
    $user = User::factory()->create([
        'employee_id' => 'EMP-AI-001',
        'office' => 'Operations',
    ]);

    EmployeeRecord::query()->create([
        'employee_id' => 'EMP-AI-001',
        'first_name' => 'Model',
        'last_name' => 'Test',
        'office' => 'Operations',
        'position' => 'Staff',
        'employment_status' => 'Permanent',
    ]);

    $entry = LearningNeedsAnalysis::query()->create([
        'user_id' => $user->id,
        'employee_id' => $user->employee_id,
        'ipcr_rating' => '3.2',
        'skill_assessments' => [
            'Technical Writing' => '1',
            'Conflict Resolution' => '1',
            'Project Planning and Scheduling' => '1',
        ],
        'supervisor_skill_assessments' => [
            'Technical Writing' => '1',
            'Conflict Resolution' => '1',
            'Project Planning and Scheduling' => '1',
        ],
        'focus_area' => 'Technical writing',
        'competency_gap' => 'Needs stronger report writing',
        'proposed_intervention' => 'Writing workshop',
        'priority_level' => 'high',
        'status' => 'reviewed',
    ]);

    $analytics = app(LnaAnalyticsService::class)->generate($entry);

    expect($analytics['analytics_model_version'])->toBe('lna-logistic-v1')
        ->and($analytics['training_needed'])->toBeTrue()
        ->and($analytics['training_need_probability'])->toBeGreaterThan(0.5)
        ->and($analytics['recommendations'])->not->toBeEmpty()
        ->and($analytics['recommendations'][0]['rank'])->toBe(1)
        ->and($analytics['recommendations'][0]['probability'])->toBeGreaterThanOrEqual($analytics['recommendations'][min(1, count($analytics['recommendations']) - 1)]['probability']);
});

test('the employee dashboard exposes reviewed predictive and prescriptive analytics', function () {
    $user = User::factory()->create([
        'employee_id' => 'EMP-AI-002',
    ]);

    $entry = LearningNeedsAnalysis::query()->create([
        'user_id' => $user->id,
        'employee_id' => $user->employee_id,
        'focus_area' => 'Technical writing',
        'competency_gap' => 'Needs stronger report writing',
        'proposed_intervention' => 'Writing workshop',
        'priority_level' => 'high',
        'status' => 'reviewed',
        'analytics_generated_at' => now(),
        'predictive_skills_gap' => 'Technical Writing',
        'prescriptive_training_recommendation' => 'Technical Writing Workshop',
        'training_needed' => true,
        'training_need_probability' => 0.84,
        'analytics_model_version' => 'lna-logistic-v1',
    ]);

    $entry->recommendations()->create([
        'rank' => 1,
        'competency_name' => 'Technical Writing',
        'competency_category' => 'Communication',
        'probability' => 0.84,
        'priority' => 'high',
        'training_title' => 'Technical Writing Workshop',
        'training_type' => 'In-house',
        'provider' => 'HRDC Learning and Development Unit',
        'recommendation_text' => 'Technical Writing has a high predicted training need.',
    ]);

    $this->actingAs($user)
        ->get('/employee')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Employee/Dashboard')
            ->where('recommendations.0.predictive_skills_gap', 'Technical Writing')
            ->where('recommendations.0.prescriptive_training_recommendation', 'Technical Writing Workshop')
            ->where('recommendations.0.confidence_score', 0.84)
            ->where('recommendations.0.model_version', 'lna-logistic-v1')
            ->where('recommendations.0.ranked_recommendations.0.training_title', 'Technical Writing Workshop')
        );
});
