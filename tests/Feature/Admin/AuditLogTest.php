<?php

use App\Http\Middleware\RecordAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('successful state changes write audit events without storing submitted values', function () {
    $user = User::factory()->create();
    $request = Request::create('/admin/employees/import', 'POST', [
        'csv' => 'employee_id,first_name,last_name\nEMP-1,Demo,Employee',
        'password' => 'should-not-be-captured',
    ]);
    $request->setUserResolver(fn () => $user);

    $response = app(RecordAuditLog::class)->handle($request, fn () => new Response('', 201));
    $event = AuditLog::query()->firstOrFail();

    expect($response->getStatusCode())->toBe(201)
        ->and($event->user_id)->toBe($user->id)
        ->and($event->action)->toBe('POST /admin/employees/import')
        ->and($event->properties)->toBe([
            'method' => 'POST',
            'path' => '/admin/employees/import',
            'route_name' => null,
            'response_status' => 201,
        ])
        ->and(json_encode($event->properties))->not->toContain('should-not-be-captured');
});

test('failed requests are not recorded as completed changes', function () {
    $request = Request::create('/admin/employees/import', 'POST');

    app(RecordAuditLog::class)->handle($request, fn () => new Response('', 422));

    expect(AuditLog::query()->count())->toBe(0);
});

test('validation redirects are not recorded as completed changes', function () {
    $request = Request::create('/admin/employees/import', 'POST');
    $session = Mockery::mock(Session::class);
    $session->shouldReceive('has')->with('errors')->andReturnTrue();
    $request->setLaravelSession($session);

    app(RecordAuditLog::class)->handle($request, fn () => new Response('', 302));

    expect(AuditLog::query()->count())->toBe(0);
});

test('system administrators can review audit events', function () {
    Role::findOrCreate('system-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('system-admin');

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'admin.employees.import',
        'description' => 'POST /admin/employees/import completed with status 302.',
        'properties' => [
            'method' => 'POST',
            'path' => '/admin/employees/import',
            'response_status' => 302,
        ],
    ]);

    $this->actingAs($admin)
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AuditLogs/Index')
            ->where('logs.data.0.action', 'admin.employees.import')
            ->where('logs.data.0.user.name', $admin->name));
});
