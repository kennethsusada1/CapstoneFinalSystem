<?php

namespace App\Http\Controllers;

use App\Models\TrainingReferral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public landing page for employees redirected from smart-pms.
 *
 * URL format:
 *   /intake?pms_user_id=17&plan=LND-REF-2026-00001&sig=...
 *
 * No authentication required — the employee may not have an L&D account yet.
 */
class IntakeController extends Controller
{
    public function show(Request $request): Response
    {
        $pmsUserId = $request->query('pms_user_id');
        $plan      = $request->query('plan');

        // Look up the referral — gracefully handle missing/invalid params
        $referral = null;

        if ($pmsUserId && $plan) {
            $referral = TrainingReferral::query()
                ->where('lnd_reference_id', $plan)
                ->where('pms_user_id', $pmsUserId)
                ->first();
        }

        return Inertia::render('Intake', [
            'employeeName'   => $referral?->employee_name,
            'employeeOffice' => $referral?->employee_office,
            'officialRating' => $referral?->official_rating,
            'periodName'     => $referral?->period_name,
            'plan'           => $plan,
            'pmsUserId'      => $pmsUserId,
            'found'          => $referral !== null,
        ]);
    }
}
