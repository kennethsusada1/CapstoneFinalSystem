<?php

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
    ->prefix('lnd')
    ->group(function (): void {
        // POST /api/lnd/development-plans
        // Receives employee IDP + IPCR data from PMS, creates a TrainingReferral.
        Route::post('development-plans', [PmsIntakeController::class, 'store'])
            ->name('api.lnd.development-plans.store');
    });
