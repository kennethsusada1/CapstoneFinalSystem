<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminActivationCredentialsMail;
use App\Models\LndTrainee;
use App\Models\TrainingApplication;
use App\Models\TrainingReferral;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles the inbound training-referral intake from smart-pms.
 *
 * Route:  POST /api/lnd/development-plans
 * Auth:   VerifyLndApiToken middleware (Bearer token)
 *
 * On success returns HTTP 201:
 *   { "status": "acknowledged", "lnd_reference_id": "LND-REF-2026-00042" }
 *
 * On validation failure returns HTTP 422:
 *   { "message": "...", "errors": { ... } }
 *
 * Auto-provisioning logic:
 *   1. Look up users by employee.email
 *   2. If found  → set pms_user_id on the existing account (no duplicate)
 *   3. If not    → create a new user with role 'employee' + send activation email
 *   4. Upsert lnd_trainees, setting lnd_user_id to the found/created user
 */
class PmsIntakeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // -----------------------------------------------------------------
        // 1. Validate the incoming payload
        // -----------------------------------------------------------------
        $validated = $request->validate([
            'external_plan_id'                => ['required', 'string', 'max:64'],
            'source_system'                   => ['required', 'string', 'max:32'],

            'period'                          => ['required', 'array'],
            'period.id'                       => ['required', 'integer'],
            'period.name'                     => ['required', 'string', 'max:128'],

            'employee'                        => ['required', 'array'],
            'employee.id'                     => ['required', 'integer'],
            'employee.name'                   => ['required', 'string', 'max:255'],
            'employee.email'                  => ['required', 'email', 'max:255'],
            'employee.position'               => ['nullable', 'string', 'max:255'],
            'employee.office_id'              => ['nullable', 'integer'],
            'employee.office_name'            => ['nullable', 'string', 'max:255'],

            'performance'                     => ['required', 'array'],
            'performance.official_score'      => ['required', 'numeric', 'min:1', 'max:5'],
            'performance.official_rating'     => ['required', 'string', 'max:64'],
            'performance.pmt_adjusted_score'  => ['nullable', 'numeric'],
            'performance.pmt_adjusted_rating' => ['nullable', 'string', 'max:64'],
            'performance.released_at'         => ['nullable', 'string'],

            'ipcr'                            => ['required', 'array'],
            'idp_rows'                        => ['required', 'array'],
            'idp_rows.*.performance_gap'      => ['nullable', 'string'],
            'idp_rows.*.developmental_activity' => ['nullable', 'string'],
        ]);

        // -----------------------------------------------------------------
        // 2. Guard against duplicate submissions (idempotent on external_plan_id)
        // -----------------------------------------------------------------
        $existing = TrainingReferral::query()
            ->where('external_plan_id', $validated['external_plan_id'])
            ->first();

        if ($existing !== null) {
            Log::info('PmsIntakeController: duplicate submission, returning existing reference.', [
                'external_plan_id' => $validated['external_plan_id'],
                'lnd_reference_id' => $existing->lnd_reference_id,
            ]);

            return response()->json([
                'status'           => 'acknowledged',
                'lnd_reference_id' => $existing->lnd_reference_id,
            ], 200);
        }

        // -----------------------------------------------------------------
        // 3. Generate the LND reference ID before the transaction
        // -----------------------------------------------------------------
        $lndReferenceId = $this->generateReferenceId();

        // -----------------------------------------------------------------
        // 4. Persist everything inside a transaction
        // -----------------------------------------------------------------
        try {
            DB::transaction(function () use ($validated, $lndReferenceId): void {
                $emp    = $validated['employee'];
                $perf   = $validated['performance'];
                $period = $validated['period'];

                // 4a. Find or create the L&D user account
                $user = $this->provisionUser($emp);

                // 4b. Upsert the cross-system identity map, linking the L&D user
                LndTrainee::query()->updateOrCreate(
                    ['pms_user_id' => $emp['id']],
                    [
                        'name'        => $emp['name'],
                        'email'       => $emp['email'],
                        'position'    => $emp['position'] ?? null,
                        'office_name' => $emp['office_name'] ?? null,
                        'lnd_user_id' => $user->id,     // ← linked immediately on intake
                    ],
                );

                // 4c. Create the training referral with full JSON snapshots
                $referral = TrainingReferral::query()->create([
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
                    'ipcr_snapshot'      => $validated['ipcr'],
                    'idp_rows'           => $validated['idp_rows'],
                    'status'             => 'received',
                ]);

                // 4d. Auto-create one TrainingApplication per IDP row so Carlos
                //     appears immediately in Secretariat's Applications inbox.
                //     training_referral_id links back to this referral so
                //     processApplication() knows to fire the PMS callback on completion.
                foreach ($validated['idp_rows'] as $index => $row) {
                    $developmentalActivity = trim((string) ($row['developmental_activity'] ?? ''));
                    $performanceGap        = trim((string) ($row['performance_gap'] ?? ''));

                    // Use developmental_activity as the training title.
                    // Fall back to a descriptive label if the field is empty.
                    $trainingTitle = $developmentalActivity !== ''
                        ? $developmentalActivity
                        : ($performanceGap !== ''
                            ? 'IDP Training: '.$performanceGap
                            : 'IDP Training — '.$emp['name'].' ('.($index + 1).')');

                    TrainingApplication::query()->create([
                        'user_id'              => $user->id,
                        'employee_id'          => $user->employee_id,
                        'training_referral_id' => $referral->id,
                        'training_title'       => $trainingTitle,
                        'training_type'        => 'In-house',
                        'provider'             => 'PMS Referral — IDP Training',
                        'office'               => $emp['office_name'] ?? $user->office,
                        'status'               => 'applied',
                        'progress_percent'     => 0,
                        'is_attended'          => false,
                    ]);
                }
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
    // Account provisioning
    // -----------------------------------------------------------------

    /**
     * Find or create the L&D user account for the incoming employee.
     *
     * - If a user with the given email already exists → update pms_user_id and reuse.
     * - If not → create a new account with role 'employee' and send an activation email.
     *
     * The activation email lets the employee set their own password before their
     * first L&D login. If mail fails we log the error but do NOT abort the intake —
     * Secretariat can resend activation manually from the Admin Users page.
     */
    private function provisionUser(array $emp): User
    {
        $existingUser = User::query()
            ->where('email', $emp['email'])
            ->first();

        if ($existingUser !== null) {
            // Reuse the existing account — just stamp pms_user_id if not already set
            if ($existingUser->pms_user_id === null) {
                $existingUser->forceFill(['pms_user_id' => $emp['id']])->save();
            }

            Log::info('PmsIntakeController: reusing existing user account.', [
                'user_id'    => $existingUser->id,
                'email'      => $existingUser->email,
                'pms_user_id' => $emp['id'],
            ]);

            return $existingUser;
        }

        // No existing account — create one
        $activationToken = Str::random(48);

        /** @var User $newUser */
        $newUser = User::query()->create([
            'name'             => $emp['name'],
            'email'            => $emp['email'],
            'office'           => $emp['office_name'] ?? null,
            'pms_user_id'      => $emp['id'],
            'password'         => Hash::make(Str::password(16, true, true, true, false)),
            'activation_token' => $activationToken,
            'email_verified_at' => null,
            'activation_sent_at' => null,
        ]);

        $newUser->assignRole('employee');

        // Build activation URL — same pattern as AdminUserController
        $activationUrl = url('/activate-account?employee_id='.urlencode((string) ($newUser->employee_id ?? $newUser->email)));

        $this->sendActivationEmail($newUser, $activationUrl, $activationToken);

        Log::info('PmsIntakeController: new user account created.', [
            'user_id'    => $newUser->id,
            'email'      => $newUser->email,
            'pms_user_id' => $emp['id'],
        ]);

        return $newUser;
    }

    /**
     * Send the activation email. Failures are logged but not re-thrown —
     * the intake itself must succeed regardless of mail delivery.
     */
    private function sendActivationEmail(User $user, string $activationUrl, string $activationToken): void
    {
        try {
            Mail::to($user->email)
                ->send(new AdminActivationCredentialsMail($user, $activationUrl, $activationToken));

            $user->forceFill(['activation_sent_at' => now()])->save();

            Log::info('PmsIntakeController: activation email sent.', ['email' => $user->email]);
        } catch (Throwable $e) {
            Log::error('PmsIntakeController: activation email failed — Secretariat can resend manually.', [
                'email'     => $user->email,
                'exception' => $e->getMessage(),
            ]);
            // Intentionally not re-throwing — intake succeeds even if mail fails.
        }
    }

    // -----------------------------------------------------------------
    // Reference ID generator
    // -----------------------------------------------------------------

    /**
     * Generate a unique reference ID: LND-REF-{year}-{5-digit-seq}
     * Uses count+1 as the sequence — safe for our throughput.
     */
    private function generateReferenceId(): string
    {
        $year = now()->year;
        $seq  = TrainingReferral::query()->count() + 1;

        return sprintf('LND-REF-%d-%05d', $year, $seq);
    }
}
