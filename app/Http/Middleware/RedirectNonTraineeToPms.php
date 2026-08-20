<?php

namespace App\Http\Middleware;

use App\Models\TrainingReferral;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the employee portal against PMS-referred employees whose training
 * is already complete (or who were never referred at all and don't belong here).
 *
 * Rule — only redirect if ALL of these are true:
 *  1. The user has a pms_user_id  (they came from PMS)
 *  2. They have at least one training_referral  (they were actually referred)
 *  3. None of those referrals is active  (status = received OR in_progress)
 *
 * This means:
 *  - Native L&D staff (secretariat, hrdc, etc.)     → pms_user_id null  → pass through
 *  - PMS employee, no referral yet                  → pass through  (provisioned but not yet referred)
 *  - PMS employee, active referral                  → pass through  (training in progress — they belong here)
 *  - PMS employee, all referrals completed/rejected → redirect to PMS (training done, go back)
 */
class RedirectNonTraineeToPms
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only applies to users that came from PMS
        if ($user === null || $user->pms_user_id === null) {
            return $next($request);
        }

        $totalReferrals = TrainingReferral::query()
            ->where('pms_user_id', $user->pms_user_id)
            ->count();

        // No referrals at all — employee was provisioned but PMS hasn't submitted
        // a referral yet (e.g. test accounts, or intake email arrived before payload).
        // Pass through rather than blocking.
        if ($totalReferrals === 0) {
            return $next($request);
        }

        // Has referrals — check if any are still active
        $hasActiveReferral = TrainingReferral::query()
            ->where('pms_user_id', $user->pms_user_id)
            ->whereIn('status', ['received', 'in_progress'])
            ->exists();

        if ($hasActiveReferral) {
            // At least one active training referral — employee belongs here
            return $next($request);
        }

        // All referrals are completed/rejected — redirect back to PMS
        $pmsBaseUrl = rtrim((string) config('services.pms.base_url'), '/');

        if (empty($pmsBaseUrl) || str_contains($pmsBaseUrl, 'REPLACE_WITH')) {
            // PMS_BASE_URL not configured yet — log and pass through rather than hard-fail
            Log::warning(
                'RedirectNonTraineeToPms: PMS_BASE_URL is not configured. Passing through.',
                ['user_id' => $user->id, 'pms_user_id' => $user->pms_user_id],
            );

            return $next($request);
        }

        return redirect()->away("{$pmsBaseUrl}/login");
    }
}
