# HRMO Hub — L&D Side Implementation Plan

> **Last updated:** August 22, 2026
> **Status:** Needs to be built. PMS side is already designed.
> **Reference:** See `docs/HRMO_HUB.md` in smart-pms for the full protocol and PMS-side design.

---

## Overview

The HRMO Hub is a mutual connection management system across HRIS pillars. PMS has a Hub admin UI at `/administrator/hris`. L&D needs to build an equivalent so that:

1. When PMS admin configures the L&D connection in PMS Hub and clicks Connect, L&D receives the request
2. L&D admin sees a pending connection request and can Accept or Reject
3. On Accept, L&D calls back to PMS to confirm — both sides mark themselves as connected

Without this, the L&D pillar in PMS Hub is one-sided — PMS just saves credentials locally with no L&D awareness.

---

## How the Handshake Works (L&D Perspective)

```
PMS admin clicks Connect for L&D pillar in PMS Hub
        │
        ▼
PMS POSTs to L&D:
  POST /api/hub/connection-request
  Authorization: Bearer {LND_API_TOKEN}
  Body: {
    "pillar": "pms",
    "base_url": "https://smart-pms.test",
    "callback_token": "pms_live_cb_2026_..."
  }
        │
        ▼
L&D stores connection request as status = 'pending'
L&D admin sees it in Hub page → clicks Accept
        │
        ▼
L&D fires back to PMS:
  POST {base_url}/api/hub/connection-accepted
  Authorization: Bearer {callback_token}  ← the token PMS sent in the request body
  Body: { "pillar": "ld", "status": "accepted" }
        │
        ▼
PMS marks its ld row as status = 'connected'
L&D marks its pms_hub_connection as status = 'connected'
Both sides are now live
```

---

## Part 1: Database

### New table: `pms_hub_connection`

One row, always. Stores the current state of the PMS ↔ L&D connection.

```php
// Migration: create_pms_hub_connection_table

Schema::create('pms_hub_connection', function (Blueprint $table) {
    $table->id();
    $table->string('status', 32)->default('disconnected');
    // 'disconnected' | 'pending' | 'connected' | 'rejected'

    // What PMS sent us in the connection request
    $table->string('pms_base_url')->nullable();       // used to call back to PMS
    $table->text('pms_callback_token')->nullable();   // Bearer token for the callback

    // Timestamps
    $table->timestamp('requested_at')->nullable();    // when PMS sent the request
    $table->timestamp('accepted_at')->nullable();     // when L&D admin accepted
    $table->timestamps();
});
```

### New model: `PmsHubConnection`

```php
// app/Models/PmsHubConnection.php

class PmsHubConnection extends Model
{
    protected $table = 'pms_hub_connection';

    protected $fillable = [
        'status',
        'pms_base_url',
        'pms_callback_token',
        'requested_at',
        'accepted_at',
    ];

    protected $hidden = ['pms_callback_token'];

    protected $casts = [
        'requested_at' => 'datetime',
        'accepted_at'  => 'datetime',
    ];

    const STATUS_DISCONNECTED = 'disconnected';
    const STATUS_PENDING      = 'pending';
    const STATUS_CONNECTED    = 'connected';
    const STATUS_REJECTED     = 'rejected';

    /**
     * Get or create the singleton row.
     */
    public static function instance(): static
    {
        return static::firstOrCreate([], ['status' => self::STATUS_DISCONNECTED]);
    }
}
```

---

## Part 2: Inbound API — Receive PMS Connection Request

### Route

```php
// routes/api.php — add alongside existing lnd routes

Route::middleware(VerifyLndApiToken::class)
    ->prefix('hub')
    ->group(function (): void {
        // POST /api/hub/connection-request
        Route::post('connection-request', [HubController::class, 'connectionRequest'])
            ->name('api.hub.connection-request');
    });
```

Uses the existing `VerifyLndApiToken` middleware — PMS sends `Authorization: Bearer {LND_API_TOKEN}` same as the IDP intake endpoint.

### Controller: `HubController`

```php
// app/Http/Controllers/Api/HubController.php

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
     * Stores credentials as 'pending' — L&D admin must accept in the Hub UI.
     *
     * POST /api/hub/connection-request
     * Auth: Bearer {LND_API_TOKEN}
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
            'status'              => PmsHubConnection::STATUS_PENDING,
            'pms_base_url'        => $validated['base_url'],
            'pms_callback_token'  => $validated['callback_token'],
            'requested_at'        => now(),
            'accepted_at'         => null,
        ]);

        Log::info('HubController: connection request received from PMS.', [
            'pms_base_url' => $validated['base_url'],
        ]);

        return response()->json(['ok' => true, 'status' => 'pending'], 200);
    }
}
```

---

## Part 3: L&D Admin Hub Page

### Route

```php
// routes/web.php — inside the admin middleware group

Route::get('/hub', [AdminHubController::class, 'index'])->name('admin.hub');
Route::post('/hub/accept', [AdminHubController::class, 'accept'])->name('admin.hub.accept');
Route::post('/hub/reject', [AdminHubController::class, 'reject'])->name('admin.hub.reject');
Route::post('/hub/disconnect', [AdminHubController::class, 'disconnect'])->name('admin.hub.disconnect');
```

### Controller: `AdminHubController`

```php
// app/Http/Controllers/Admin/AdminHubController.php

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
        ]);
    }

    /**
     * L&D admin accepts the PMS connection request.
     * Fires callback to PMS to confirm, then marks connection as 'connected'.
     */
    public function accept(): RedirectResponse
    {
        $connection = PmsHubConnection::instance();

        if ($connection->status !== PmsHubConnection::STATUS_PENDING) {
            return back()->withErrors(['message' => 'No pending connection request to accept.']);
        }

        // Fire callback to PMS
        $callbackSuccess = $this->notifyPms($connection, 'accepted');

        $connection->update([
            'status'      => PmsHubConnection::STATUS_CONNECTED,
            'accepted_at' => now(),
        ]);

        $message = $callbackSuccess
            ? 'PMS connection accepted. Both systems are now connected.'
            : 'Connection accepted locally, but could not reach PMS to confirm. PMS may still show pending — retry from PMS side.';

        return back()->with('success', $message);
    }

    /**
     * L&D admin rejects the PMS connection request.
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
     * Disconnect from PMS.
     */
    public function disconnect(): RedirectResponse
    {
        PmsHubConnection::instance()->update([
            'status'              => PmsHubConnection::STATUS_DISCONNECTED,
            'pms_base_url'        => null,
            'pms_callback_token'  => null,
            'requested_at'        => null,
            'accepted_at'         => null,
        ]);

        return back()->with('success', 'Disconnected from PMS.');
    }

    /**
     * Fire the accept/reject callback to PMS.
     * Returns true on success, false on failure (non-blocking).
     */
    private function notifyPms(PmsHubConnection $connection, string $status): bool
    {
        if (! $connection->pms_base_url || ! $connection->pms_callback_token) {
            return false;
        }

        try {
            $response = Http::withToken($connection->pms_callback_token)
                ->timeout(10)
                ->post(
                    rtrim($connection->pms_base_url, '/') . '/api/hub/connection-accepted',
                    ['pillar' => 'ld', 'status' => $status]
                );

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('AdminHubController: failed to notify PMS of ' . $status . '.', [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
```

---

## Part 4: Frontend Page — `Admin/Hub/Index` (TSX)

The page should show one card for PMS with the current connection status and action buttons.

### Props shape
```ts
interface Props {
    pmsConnection: {
        status: 'disconnected' | 'pending' | 'connected' | 'rejected';
        pms_base_url: string | null;
        requested_at: string | null;  // e.g. "3 minutes ago"
        accepted_at: string | null;
    };
}
```

### Status display logic

| Status | Badge color | Available actions |
|---|---|---|
| `disconnected` | Gray — Not Connected | None (waiting for PMS to initiate) |
| `pending` | Yellow — Pending Acceptance | Accept, Reject |
| `connected` | Green — Connected | Disconnect |
| `rejected` | Red — Rejected | (PMS can re-send request) |

### Key UI notes
- Match PMS Hub styling: dark card, slide-in panel or inline actions
- Show `pms_base_url` when status is `pending` so the admin knows who is connecting
- Show `requested_at` on pending, `accepted_at` on connected
- Accept/Reject are POST forms via Inertia `router.post()`
- L&D has no ability to initiate — it only receives and responds

---

## Part 5: How This Connects to the IDP Handoff

Once the Hub connection is established, the L&D integration config can be read from `pms_hub_connection` instead of only from `.env`. But this is a future step — keep `.env` working until the Hub is fully tested.

For now, just having the Hub UI and handshake working is enough. The actual IDP data flow (`POST /api/lnd/development-plans` and the callback) continues to use the tokens already in `.env`.

---

## Part 6: Implementation Checklist

- [ ] Migration: `create_pms_hub_connection_table`
- [ ] Model: `app/Models/PmsHubConnection.php`
- [ ] API controller: `app/Http/Controllers/Api/HubController.php` with `connectionRequest()`
- [ ] Admin controller: `app/Http/Controllers/Admin/AdminHubController.php` with `index()`, `accept()`, `reject()`, `disconnect()`
- [ ] Add API route: `POST /api/hub/connection-request` under `VerifyLndApiToken`
- [ ] Add web routes: `/admin/hub`, `/admin/hub/accept`, `/admin/hub/reject`, `/admin/hub/disconnect`
- [ ] Frontend: `resources/js/pages/Admin/Hub/Index.tsx` — PMS connection card with status badges and action buttons

---

## Part 7: Files That Do NOT Need to Change

- `routes/api.php` existing IDP routes — no change
- `app/Services/PmsCallbackService.php` — no change
- `app/Http/Middleware/VerifyLndApiToken.php` — reused as-is for the new Hub route
- All existing training workflow controllers — no change

---

*Document created: August 22, 2026*
*Counterpart: `docs/HRMO_HUB.md` in smart-pms*
