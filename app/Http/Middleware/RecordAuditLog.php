<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordAuditLog
{
    /**
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);
        $hasValidationErrors = $request->hasSession() && $request->session()->has('errors');

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400
            || $hasValidationErrors) {
            return $response;
        }

        $route = $request->route();
        $subject = collect($route?->parameters() ?? [])->first(fn (mixed $value): bool => $value instanceof Model);
        $path = '/'.ltrim($request->path(), '/');
        $method = $request->method();

        try {
            AuditLog::query()->create([
                'user_id' => $request->user()?->getAuthIdentifier(),
                'action' => $route?->getName() ?: $method.' '.$path,
                'entity_type' => $subject instanceof Model ? $subject::class : null,
                'entity_id' => $subject instanceof Model && is_numeric($subject->getKey())
                    ? (int) $subject->getKey()
                    : null,
                'description' => "{$method} {$path} completed with status {$response->getStatusCode()}.",
                'properties' => [
                    'method' => $method,
                    'path' => $path,
                    'route_name' => $route?->getName(),
                    'response_status' => $response->getStatusCode(),
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to record an audit event.', [
                'route' => $route?->getName(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $response;
    }
}
