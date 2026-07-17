<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $roles = $user->getRoleNames();

        if ($roles->contains('employee')) {
            return $next($request);
        }

        if ($roles->intersect(['system-admin', 'secretariat', 'hrdc', 'supervisor'])->isNotEmpty()) {
            abort(403);
        }

        if (filled($user->employee_id)) {
            if ($roles->isEmpty()) {
                $user->assignRole('employee');
            }

            return $next($request);
        }

        abort(403);
    }
}
