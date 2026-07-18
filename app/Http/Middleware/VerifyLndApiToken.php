<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound requests from smart-pms to our API endpoints.
 *
 * Expects:  Authorization: Bearer {LND_API_TOKEN}
 * Env key:  LND_API_TOKEN  (stored in .env → config('services.lnd.api_token'))
 *
 * Responds with 401 JSON on missing token, 403 JSON on invalid token.
 * Uses hash_equals() to prevent timing-attack comparisons.
 */
class VerifyLndApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.lnd.api_token');

        if (empty($configured)) {
            // Misconfigured server — fail closed rather than open
            return response()->json(['message' => 'API authentication is not configured.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $provided = $request->bearerToken();

        if ($provided === null) {
            return response()->json(['message' => 'Unauthenticated. Bearer token required.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! hash_equals($configured, $provided)) {
            return response()->json(['message' => 'Forbidden. Invalid API token.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
