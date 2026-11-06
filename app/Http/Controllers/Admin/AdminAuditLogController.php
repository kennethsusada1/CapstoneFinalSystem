<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => AuditLog::query()
                ->with('user:id,name,employee_id')
                ->latest('id')
                ->paginate(25)
                ->through(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'description' => $log->description,
                    'properties' => $log->properties,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'user' => $log->user ? [
                        'name' => $log->user->name,
                        'employee_id' => $log->user->employee_id,
                    ] : null,
                ]),
        ]);
    }
}
