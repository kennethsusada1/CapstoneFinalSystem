<?php

use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['system-admin', 'secretariat', 'hrdc', 'supervisor', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('admin can view employee records page', function () {
    $admin = User::factory()->create([
        'employee_id' => 'ADM-001',
    ]);
    $admin->syncRoles(['system-admin']);

    EmployeeRecord::query()->create([
        'employee_id' => 'EMP-901',
        'first_name' => 'Anne',
        'last_name' => 'Santos',
        'email' => 'anne.santos@example.com',
        'office' => 'HR Office',
        'position' => 'Staff',
        'employment_status' => 'Active',
        'source' => 'HRMS Import',
        'last_imported_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/employees');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Employees/Index')
        ->has('employees', 1)
        ->where('employees.0.employee_id', 'EMP-901'),
    );
});

test('admin can update employee records', function () {
    $admin = User::factory()->create([
        'employee_id' => 'ADM-001',
    ]);
    $admin->syncRoles(['system-admin']);

    $employee = EmployeeRecord::query()->create([
        'employee_id' => 'EMP-902',
        'first_name' => 'Maria',
        'last_name' => 'Cruz',
        'email' => 'maria.cruz@example.com',
        'office' => 'Operations',
        'position' => 'Clerk',
        'employment_status' => 'Active',
        'source' => 'HRMS Import',
        'last_imported_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patch("/admin/employees/{$employee->id}", [
            'first_name' => 'Maria',
            'last_name' => 'Cruz',
            'middle_name' => 'Lopez',
            'email' => 'maria.cruz@example.com',
            'office' => 'Learning Office',
            'position' => 'Senior Clerk',
            'employment_status' => 'Inactive',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $employee->refresh();

    expect($employee->middle_name)->toBe('Lopez');
    expect($employee->office)->toBe('Learning Office');
    expect($employee->position)->toBe('Senior Clerk');
    expect($employee->employment_status)->toBe('Inactive');
});
