<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PmsHubConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminHubController extends Controller
{
    /**
     * Show the Hub admin page.
     * Displays the current PMS connection status and available actions.
     */
    public function index(): Response
    {
        $connection = PmsHubConnection::instance();

        return Inertia::render('Admin/Hub/Index', [
            'pmsConnection' => [
                'status'       => $connection->status,
                'pms_base_url' => $connection->pms_base_url,
                'requested_at' => $connection->requested_at?->diffForHumans(),
                'accepted_at'  => $connection->accepted_at?->diffForHumans(),
            ],
            // Credentials the PMS admin needs to configure their side.
            // LND_API_TOKEN is what PMS uses as Bearer when calling us.
            // base_url is our own APP_URL — PMS sets this as LND_BASE_URL.
            'lndCredentials' => [
                'base_url'   => config('app.url'),
                'api_token'  => config('services.lnd.api_token'),
                'hmac_secret'=> config('services.lnd.redirect_hmac_secret'),
            ],
        ]);
    }

    /**
     * L&D admin accepts the PMS connection request.
     * Notifies PMS via callback, then marks local status as connected.
     */
    public function accept(): RedirectResponse
    {
        $connection = PmsHubConnection::instance();

        if ($connection->status !== PmsHubConnection::STATUS_PENDING) {
            return back()->withErrors(['message' => 'No pending connection request to accept.']);
        }

        $callbackOk = $this->notifyPms($connection, 'accepted');

        $connection->update([
            'status'      => PmsHubConnection::STATUS_CONNECTED,
            'accepted_at' => now(),
        ]);

        $message = $callbackOk
            ? 'PMS connection accepted. Both systems are now connected.'
            : 'Accepted locally, but could not reach PMS to confirm. PMS may still show pending — ask the PMS admin to re-test the connection.';

        return back()->with('success', $message);
    }

    /**
     * L&D admin rejects the PMS connection request.
     * Notifies PMS and clears stored credentials.
     */
    public function reject(): RedirectResponse
    {
        $connection = PmsHubConnection::instance();

        $this->notifyPms($connection, 'rejected');

        $connection->update([
            'status'             => PmsHubConnection::STATUS_REJECTED,
            'pms_callback_token' => null,
        ]);

        return back()->with('success', 'Connection request rejected.');
    }

    /**
     * Disconnect from PMS and clear all stored credentials.
     */
    public function disconnect(): RedirectResponse
    {
        PmsHubConnection::instance()->update([
            'status'             => PmsHubConnection::STATUS_DISCONNECTED,
            'pms_base_url'       => null,
            'pms_callback_token' => null,
            'requested_at'       => null,
            'accepted_at'        => null,
        ]);

        return back()->with('success', 'Disconnected from PMS.');
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * POST the accept/reject decision back to PMS.
     * Non-blocking — logs failure but never throws.
     *
     * @param  string  $status  'accepted' | 'rejected'
     */
    private function notifyPms(PmsHubConnection $connection, string $status): bool
    {
        if (! $connection->pms_base_url || ! $connection->pms_callback_token) {
            return false;
        }

        try {
            $response = Http::withToken($connection->pms_callback_token)
                ->timeout(10)
                ->post(rtrim($connection->pms_base_url, '/') . '/api/hub/connection-accepted', [
                    'pillar' => 'ld',
                    'status' => $status,
                ]);

            if (! $response->successful()) {
                Log::warning('AdminHubController: PMS callback returned non-2xx.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('AdminHubController: failed to notify PMS of ' . $status . '.', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
