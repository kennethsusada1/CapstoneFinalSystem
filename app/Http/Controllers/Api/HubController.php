<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmsHubConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HubController extends Controller
{
    /**
     * Receive a connection request from smart-pms.
     *
     * PMS calls this when an admin fills in the L&D URL and token in their
     * HRMO Hub page and clicks Connect. The request is stored as 'pending'
     * until an L&D admin accepts it via the Admin Hub UI.
     *
     * Route:  POST /api/hub/connection-request
     * Auth:   VerifyLndApiToken (Bearer LND_API_TOKEN)
     */
    public function connectionRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pillar'         => ['required', 'string', 'in:pms'],
            'base_url'       => ['required', 'url', 'max:255'],
            'callback_token' => ['required', 'string', 'min:8'],
        ]);

        $connection = PmsHubConnection::instance();
        $connection->update([
            'status'             => PmsHubConnection::STATUS_PENDING,
            'pms_base_url'       => $validated['base_url'],
            'pms_callback_token' => $validated['callback_token'],
            'requested_at'       => now(),
            'accepted_at'        => null,
        ]);

        Log::info('HubController: connection request received from PMS.', [
            'pms_base_url' => $validated['base_url'],
        ]);

        return response()->json(['ok' => true, 'status' => 'pending']);
    }

    /**
     * Receive a disconnect notification from smart-pms.
     *
     * PMS calls this when the admin clicks Disconnect in their HRMO Hub.
     * Resets the local connection state to disconnected.
     *
     * Route:  POST /api/hub/disconnect
     * Auth:   VerifyLndApiToken (Bearer LND_API_TOKEN)
     */
    public function disconnect(Request $request): JsonResponse
    {
        PmsHubConnection::instance()->update([
            'status'             => PmsHubConnection::STATUS_DISCONNECTED,
            'pms_base_url'       => null,
            'pms_callback_token' => null,
            'requested_at'       => null,
            'accepted_at'        => null,
        ]);

        Log::info('HubController: disconnect notification received from PMS.');

        return response()->json(['ok' => true]);
    }
}
