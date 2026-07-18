<?php

namespace App\Services;

use App\Models\TrainingReferral;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires the outbound training-completion callback to smart-pms.
 *
 * Called by SecretariatPortalController when a TrainingApplication whose
 * training_referral_id is set is moved to status = 'completed'.
 *
 * Endpoint (PMS side):
 *   POST {PMS_BASE_URL}/api/lnd-callback/complete-training
 *   Authorization: Bearer {PMS_CALLBACK_TOKEN}
 *
 * On success  → records pms_notified_at on the TrainingReferral.
 * On failure  → records the error in pms_notify_error so Secretariat
 *               can see it and retry manually.
 */
class PmsCallbackService
{
    /**
     * Notify smart-pms that an employee's training is complete.
     *
     * @param  TrainingReferral  $referral  The referral whose employee finished training.
     * @param  string|null       $completedAt  ISO 8601 timestamp; defaults to now().
     * @param  list<array{course_code:string,title:string,completed_at:string}>  $coursesCompleted
     * @param  string|null       $trainerRemarks
     */
    public function notifyComplete(
        TrainingReferral $referral,
        ?string $completedAt = null,
        array $coursesCompleted = [],
        ?string $trainerRemarks = null,
    ): void {
        $baseUrl = rtrim((string) config('services.pms.base_url'), '/');
        $token   = (string) config('services.pms.callback_token');

        if (empty($baseUrl) || empty($token)) {
            Log::warning('PmsCallbackService: PMS_BASE_URL or PMS_CALLBACK_TOKEN is not configured. Skipping callback.', [
                'training_referral_id' => $referral->id,
                'lnd_reference_id'     => $referral->lnd_reference_id,
            ]);

            return;
        }

        $payload = [
            'pms_user_id'      => $referral->pms_user_id,
            'lnd_reference_id' => $referral->lnd_reference_id,
            'external_plan_id' => $referral->external_plan_id,
            'completed_at'     => $completedAt ?? now()->toIso8601String(),
            'courses_completed' => $coursesCompleted,
            'trainer_remarks'  => $trainerRemarks,
        ];

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('services.pms.timeout', 20))
                ->acceptJson()
                ->post("{$baseUrl}/api/lnd-callback/complete-training", $payload);

            if ($response->successful()) {
                $referral->update([
                    'status'          => 'completed',
                    'completed_at'    => $completedAt ?? now(),
                    'pms_notified_at' => now(),
                    'pms_notify_error' => null,
                ]);

                Log::info('PmsCallbackService: callback succeeded.', [
                    'training_referral_id' => $referral->id,
                    'lnd_reference_id'     => $referral->lnd_reference_id,
                    'pms_response_status'  => $response->status(),
                ]);
            } else {
                $errorMessage = "HTTP {$response->status()}: " . $response->body();

                $referral->update([
                    'pms_notify_error' => $errorMessage,
                ]);

                Log::error('PmsCallbackService: callback returned non-2xx.', [
                    'training_referral_id' => $referral->id,
                    'lnd_reference_id'     => $referral->lnd_reference_id,
                    'status'               => $response->status(),
                    'body'                 => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            $referral->update([
                'pms_notify_error' => $e->getMessage(),
            ]);

            Log::error('PmsCallbackService: callback threw an exception.', [
                'training_referral_id' => $referral->id,
                'lnd_reference_id'     => $referral->lnd_reference_id,
                'exception'            => $e->getMessage(),
            ]);
        }
    }
}
