<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LndTrainee;
use App\Models\TrainingReferral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles the inbound training-referral intake from smart-pms.
 *
 * Route:  POST /api/lnd/development-plans
 * Auth:   VerifyLndApiToken middleware (Bearer token)
 *
 * On success returns HTTP 201 with:
 *   { "status": "acknowledged", "lnd_reference_id": "LND-REF-2026-00042" }
 *
 * On validation failure returns HTTP 422 with:
 *   { "message": "...", "errors": { ... } }
 */
class PmsIntakeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // -----------------------------------------------------------------
        // 1. Validate the incoming payload
        // -----------------------------------------------------------------
        $validated = $request->validate([
            'external_plan_id'               => ['required', 'string', 'max:64'],
            'source_system'                  => ['required', 'string', 'max:32'],

            'period'                         => ['required', 'array'],
            'period.id'                      => ['required', 'integer'],
            'period.name'                    => ['required', 'string', 'max:128'],

            'employee'                       => ['required', 'array'],
            'employee.id'                    => ['required', 'integer'],
            'employee.name'                  => ['required', 'string', 'max:255'],
            'employee.email'                 => ['required', 'email', 'max:255'],
            'employee.position'              => ['nullable', 'string', 'max:255'],
            'employee.office_id'             => ['nullable', 'integer'],
            'employee.office_name'           => ['nullable', 'string', 'max:255'],

            'performance'                    => ['required', 'array'],
            'performance.official_score'     => ['required', 'numeric', 'min:1', 'max:5'],
            'performance.official_rating'    => ['required', 'string', 'max:64'],
            'performance.pmt_adjusted_score' => ['nullable', 'numeric'],
            'performance.pmt_adjusted_rating'=> ['nullable', 'string', 'max:64'],
            'performance.released_at'        => ['nullable', 'string'],

            'ipcr'                           => ['required', 'array'],
            'idp_rows'                       => ['required', 'array'],
            'idp_rows.*.performance_gap'     => ['nullable', 'string'],
            'idp_rows.*.developmental_activity' => ['nullable', 'string'],
        ]);

        // -----------------------------------------------------------------
        // 2. Guard against duplicate submissions (idempotent on external_plan_id)
        // -----------------------------------------------------------------
        $existing = TrainingReferral::query()
            ->where('external_plan_id', $validated['external_plan_id'])
            ->first();

        if ($existing !== null) {
            Log::info('PmsIntakeController: duplicate submission received, returning existing reference.', [
                'external_plan_id' => $validated['external_plan_id'],
                'lnd_reference_id' => $existing->lnd_reference_id,
            ]);

            return response()->json([
                'status'           => 'acknowledged',
                'lnd_reference_id' => $existing->lnd_reference_id,
            ], 200);
        }

        // -----------------------------------------------------------------
        // 3. Generate the LND reference ID
        // -----------------------------------------------------------------
        $lndReferenceId = $this->generateReferenceId();

        // -----------------------------------------------------------------
        // 4. Persist inside a transaction
        // -----------------------------------------------------------------
        try {
            DB::transaction(function () use ($validated, $lndReferenceId): void {
                $emp = $validated['employee'];
                $perf = $validated['performance'];
                $period = $validated['period'];

                // 4a. Upsert the cross-system identity map
                LndTrainee::query()->updateOrCreate(
                    ['pms_user_id' => $emp['id']],
                    [
                        'name'        => $emp['name'],
                        'email'       => $emp['email'],
                        'position'    => $emp['position'] ?? null,
                        'office_name' => $emp['office_name'] ?? null,
                        // lnd_user_id is intentionally left untouched — set manually by Secretariat
                    ],
                );

                // 4b. Create the training referral with full JSON snapshots
                TrainingReferral::query()->create([
                    'lnd_reference_id'   => $lndReferenceId,
                    'external_plan_id'   => $validated['external_plan_id'],
                    'source_system'      => $validated['source_system'],
                    'pms_user_id'        => $emp['id'],
                    'pms_period_id'      => $period['id'],
                    'period_name'        => $period['name'],
                    'employee_name'      => $emp['name'],
                    'employee_email'     => $emp['email'],
                    'employee_position'  => $emp['position'] ?? null,
                    'employee_office_id' => $emp['office_id'] ?? null,
                    'employee_office'    => $emp['office_name'] ?? null,
                    'official_score'     => $perf['official_score'],
                    'official_rating'    => $perf['official_rating'],
                    'ipcr_snapshot'      => $validated['ipcr'],     // full ipcr block as-received
                    'idp_rows'           => $validated['idp_rows'], // full idp_rows array as-received
                    'status'             => 'received',
                ]);
            });
        } catch (Throwable $e) {
            Log::error('PmsIntakeController: failed to persist referral.', [
                'external_plan_id' => $validated['external_plan_id'],
                'exception'        => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal server error while processing the referral.',
            ], 500);
        }

        Log::info('PmsIntakeController: referral created.', [
            'external_plan_id' => $validated['external_plan_id'],
            'lnd_reference_id' => $lndReferenceId,
            'pms_user_id'      => $validated['employee']['id'],
        ]);

        return response()->json([
            'status'           => 'acknowledged',
            'lnd_reference_id' => $lndReferenceId,
        ], 201);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Generate a unique, sequential reference ID in the format LND-REF-{year}-{5-digit-seq}.
     * Uses the current count of training_referrals as the sequence base (1-indexed).
     * Padded to 5 digits — enough for 99,999 referrals per year before collision risk.
     */
    private function generateReferenceId(): string
    {
        $year = now()->year;
        $seq  = TrainingReferral::query()->count() + 1;

        return sprintf('LND-REF-%d-%05d', $year, $seq);
    }
}
