<?php

use App\Mail\AdminActivationCredentialsMail;
use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['system-admin', 'secretariat', 'hrdc', 'supervisor', 'employee'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('admin can create an employee account and send activation credentials', function () {
    Mail::fake();

    $admin = User::factory()->create([
        'employee_id' => 'ADM-001',
    ]);
    $admin->syncRoles(['system-admin']);

    $employee = EmployeeRecord::query()->create([
        'employee_id' => 'EMP-999',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.com',
        'office' => 'HR Office',
        'position' => 'Staff',
        'employment_status' => 'Active',
        'source' => 'HRMS Import',
        'last_imported_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/admin/users', [
            'employee_id' => $employee->employee_id,
            'role' => 'employee',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user = User::query()->where('employee_id', $employee->employee_id)->firstOrFail();

    expect($user->activation_sent_at)->not->toBeNull();
    expect($user->activation_token)->not->toBeNull();

    Mail::assertSent(AdminActivationCredentialsMail::class, function (AdminActivationCredentialsMail $mail) use ($user) {
        return $mail->user->is($user)
            && $mail->activationToken === $user->activation_token
            && str_contains($mail->activationUrl, '/activate-account')
            && str_contains($mail->activationUrl, urlencode($user->employee_id));
    });
});

test('activation login screen reflects redirect token and email', function () {
    $response = $this->get('/login?mode=activate-complete&token=abc123&email=jane.doe%40example.com');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Auth/Login')
        ->where('mode', 'activate-complete')
        ->where('token', 'abc123')
        ->where('email', 'jane.doe@example.com'),
    );
});

test('activation entry route renders activation verify screen', function () {
    $response = $this->get('/activate-account?employee_id=EMP-999');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Auth/Login')
        ->where('mode', 'activate-verify'),
    );
});

test('activation verify forces navigation to complete step for inertia requests', function () {
    $user = User::factory()->create([
        'email' => 'verify.employee@example.com',
        'employee_id' => 'EMP-777',
        'activation_token' => 'verify-token-777',
        'email_verified_at' => null,
    ]);

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->post('/send/id', [
            'employee_id' => $user->employee_id,
        ]);

    $response
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/activate-account/complete').'?token=verify-token-777&email='.urlencode($user->email));
});

test('employee can complete activation with profile details and medium-strength password', function () {
    $employee = EmployeeRecord::query()->create([
        'employee_id' => 'EMP-123',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.activation@example.com',
        'office' => 'Old Office',
        'position' => 'Staff',
        'employment_status' => 'Active',
        'source' => 'HRMS Import',
        'last_imported_at' => now(),
    ]);

    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => $employee->email,
        'employee_id' => $employee->employee_id,
        'activation_token' => 'activate-token-123',
        'email_verified_at' => null,
    ]);

    $response = $this->post('/activate/complete', [
        'token' => 'activate-token-123',
        'email' => $employee->email,
        'name' => 'Jane A. Doe',
        'address' => '123 Mabini Street',
        'office' => 'Learning Office',
        'password' => 'Secure123',
        'password_confirmation' => 'Secure123',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/activate-account/sign-in');

    $user->refresh();
    $employee->refresh();

    expect($user->name)->toBe('Jane A. Doe');
    expect($user->address)->toBe('123 Mabini Street');
    expect($user->office)->toBe('Learning Office');
    expect($user->activation_token)->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($employee->office)->toBe('Learning Office');
});

test('activation requires a medium-strength password', function () {
    $user = User::factory()->create([
        'email' => 'weakpass@example.com',
        'employee_id' => 'EMP-124',
        'activation_token' => 'activate-token-124',
        'email_verified_at' => null,
    ]);

    $response = $this->from('/activate-account/complete')->post('/activate/complete', [
        'token' => 'activate-token-124',
        'email' => $user->email,
        'name' => 'Weak Pass User',
        'address' => '456 Rizal Avenue',
        'office' => 'QA Office',
        'password' => 'weakpass',
        'password_confirmation' => 'weakpass',
    ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/activate-account/complete');
});

test('admin can reassign a managed user role', function () {
    $admin = User::factory()->create([
        'employee_id' => 'ADM-001',
    ]);
    $admin->syncRoles(['system-admin']);

    $user = User::factory()->create([
        'employee_id' => 'EMP-500',
    ]);
    $user->syncRoles(['employee']);

    $response = $this
        ->actingAs($admin)
        ->patch("/admin/users/{$user->id}/role", [
            'role' => 'supervisor',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->fresh()->hasRole('supervisor'))->toBeTrue();
});

test('admin cannot assign system admin through managed role update', function () {
    $admin = User::factory()->create([
        'employee_id' => 'ADM-001',
    ]);
    $admin->syncRoles(['system-admin']);

    $user = User::factory()->create([
        'employee_id' => 'EMP-501',
    ]);
    $user->syncRoles(['employee']);

    $response = $this
        ->actingAs($admin)
        ->patch("/admin/users/{$user->id}/role", [
            'role' => 'system-admin',
        ]);

    $response
        ->assertSessionHasErrors('role')
        ->assertRedirect();
});
