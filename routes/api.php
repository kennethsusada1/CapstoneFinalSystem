<?php

use App\Http\Controllers\Api\HubController;
use App\Http\Controllers\Api\PmsIntakeController;
use App\Http\Middleware\VerifyLndApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — PMS ↔ L&D Integration
|--------------------------------------------------------------------------
|
| These routes are stateless (no session/cookie) and are authenticated
| via static Bearer tokens defined in .env.
|
| All routes here are automatically prefixed with /api by the route
| registration in bootstrap/app.php.
|
*/

// -----------------------------------------------------------------------
// Inbound: smart-pms → L&D
// Authenticated by LND_API_TOKEN (L&D owns this token; PMS sends it)
// -----------------------------------------------------------------------
Route::middleware(VerifyLndApiToken::class)
    ->group(function (): void {
        // POST /api/lnd/development-plans
        // Receives employee IDP + IPCR data from PMS, creates a TrainingReferral.
        Route::prefix('lnd')->group(function (): void {
            Route::post('development-plans', [PmsIntakeController::class, 'store'])
                ->name('api.lnd.development-plans.store');
        });

        // POST /api/hub/connection-request
        // Receives HRMO Hub connection request from PMS.
        // PMS admin initiates this; L&D admin then accepts/rejects via the Hub UI.
        Route::prefix('hub')->group(function (): void {
            Route::post('connection-request', [HubController::class, 'connectionRequest'])
                ->name('api.hub.connection-request');
            Route::post('disconnect', [HubController::class, 'disconnect'])
                ->name('api.hub.disconnect');
        });
    });
