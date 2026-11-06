<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated employees are redirected to their role dashboard', function () {
    Role::findOrCreate('employee', 'web');
    $user = User::factory()->create();
    $user->assignRole('employee');
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect('/employee');
});
