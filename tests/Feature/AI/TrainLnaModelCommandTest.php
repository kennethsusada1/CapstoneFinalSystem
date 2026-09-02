<?php

use App\Models\LearningNeedsAnalysis;
use App\Models\LnaModelTrainingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('automatic training waits until the database has enough reviewed rows', function () {
    $user = User::factory()->create();

    LearningNeedsAnalysis::query()->create([
        'user_id' => $user->id,
        'employee_id' => $user->employee_id,
        'submitted_on' => now()->subYear()->toDateString(),
        'skill_assessments' => ['Technical Writing' => '1'],
        'supervisor_skill_assessments' => ['Technical Writing' => '1'],
        'focus_area' => 'Technical writing',
        'competency_gap' => 'Needs stronger report writing',
        'proposed_intervention' => 'Writing workshop',
        'priority_level' => 'high',
        'status' => 'reviewed',
    ]);

    $this->artisan('lna:train')
        ->expectsOutputToContain('waiting for at least')
        ->assertSuccessful();

    expect(LnaModelTrainingRun::query()->latest('id')->first())
        ->status->toBe('skipped')
        ->source_rows->toBe(1)
        ->positive_rows->toBe(1)
        ->negative_rows->toBe(0);
});
