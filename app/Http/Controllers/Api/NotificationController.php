<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningDevelopmentPlan;
use App\Models\LearningNeedsAnalysis;
use App\Models\TrainingApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $readIds = $this->readIds($request);

        return response()->json(
            $this->notificationsFor($request->user())
                ->map(fn (array $notification) => [
                    ...$notification,
                    'is_read' => $readIds->contains($notification['id']),
                ])
                ->values(),
        );
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $readIds = $this->readIds($request)
            ->push($id)
            ->unique()
            ->values()
            ->all();

        $request->session()->put('workflow_notification_read_ids', $readIds);

        return response()->json(['id' => $id, 'ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $ids = $this->notificationsFor($request->user())->pluck('id')->all();
        $request->session()->put('workflow_notification_read_ids', $ids);

        return response()->json(['ok' => true]);
    }

    /**
     * @return Collection<int, int>
     */
    private function readIds(Request $request): Collection
    {
        $stored = $request->session()->get('workflow_notification_read_ids', []);

        if (! is_array($stored)) {
            return collect();
        }

        return collect($stored)
            ->filter(fn (mixed $id): bool => is_int($id))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function notificationsFor(User $user): Collection
    {
        return match (true) {
            $user->hasRole('supervisor') => $this->supervisorNotifications($user),
            $user->hasRole('secretariat') => $this->secretariatNotifications(),
            $user->hasRole('hrdc') => $this->hrdcNotifications(),
            $user->hasRole('employee') => $this->employeeNotifications($user),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function supervisorNotifications(User $supervisor): Collection
    {
        return LearningNeedsAnalysis::query()
            ->with('user.employeeRecord')
            ->where('status', 'submitted')
            ->latest('submitted_on')
            ->get()
            ->take(20)
            ->map(fn (LearningNeedsAnalysis $entry) => $this->notification(
                100000 + $entry->id,
                'lna.submitted',
                'warning',
                'LNA Assessment for Evaluation',
                "{$entry->user->name} submitted an LNA assessment for {$entry->focus_area}.",
                '/supervisor/lna-reviews',
                $entry->created_at,
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function secretariatNotifications(): Collection
    {
        $reviewedAssessments = LearningNeedsAnalysis::query()
            ->with(['user', 'reviewer'])
            ->where('status', 'reviewed')
            ->latest('reviewed_at')
            ->take(20)
            ->get()
            ->map(function (LearningNeedsAnalysis $assessment) {
                $reviewer = $assessment->reviewed_by
                    ? $assessment->reviewer->name
                    : 'The employee supervisor';
                $remarks = filled($assessment->review_remarks)
                    ? " Remarks: {$assessment->review_remarks}"
                    : '';

                return $this->notification(
                    150000 + $assessment->id,
                    'lna.reviewed_by_supervisor',
                    'success',
                    'Employee LNA Evaluated',
                    "{$reviewer} endorsed {$assessment->user->name}'s {$assessment->focus_area} assessment.{$remarks}",
                    '/secretariat/applications',
                    $assessment->reviewed_at ?? $assessment->updated_at,
                );
            });

        $applications = TrainingApplication::query()
            ->with(['user', 'learningDevelopmentPlan'])
            ->latest()
            ->get();

        $pending = $applications
            ->where('secretariat_status', 'pending')
            ->take(20)
            ->map(fn (TrainingApplication $application) => $this->notification(
                200000 + $application->id,
                'training_application.submitted',
                'warning',
                'Training Application Received',
                "{$application->user->name} applied for {$application->training_title}.",
                '/secretariat/applications',
                $application->created_at,
            ));

        $planning = $applications
            ->where('secretariat_status', 'processed')
            ->where('status', 'applied')
            ->filter(fn (TrainingApplication $application) => $application->learningDevelopmentPlan === null)
            ->take(20)
            ->map(fn (TrainingApplication $application) => $this->notification(
                300000 + $application->id,
                'ld_plan.required',
                'info',
                'L&D Plan Required',
                "Prepare and submit the L&D Plan for {$application->user->name}'s {$application->training_title} request.",
                '/secretariat/ld-plans',
                $application->processed_at ?? $application->updated_at,
            ));

        return $reviewedAssessments
            ->merge($pending)
            ->merge($planning)
            ->sortByDesc('timestamp')
            ->take(20)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function hrdcNotifications(): Collection
    {
        return LearningDevelopmentPlan::query()
            ->with('trainingApplication.user')
            ->where('status', 'submitted')
            ->whereIn('review_status', ['pending', 'under-review'])
            ->latest('submitted_at')
            ->take(20)
            ->get()
            ->map(function (LearningDevelopmentPlan $plan) {
                $source = $plan->trainingApplication;
                $body = $source
                    ? "Evaluate {$source->training_title} proposed for {$source->user->name}."
                    : "Evaluate the proposed programs in {$plan->title}.";

                return $this->notification(
                    400000 + $plan->id,
                    'ld_plan.submitted',
                    'warning',
                    'L&D Plan Awaiting HRDC Decision',
                    $body,
                    "/hrdc/ld-plans/{$plan->id}",
                    $plan->submitted_at ?? $plan->created_at,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function employeeNotifications(User $employee): Collection
    {
        $notifications = collect();
        $lnaEntries = LearningNeedsAnalysis::query()
            ->with('trainingApplications')
            ->where('user_id', $employee->id)
            ->latest()
            ->get();

        foreach ($lnaEntries as $entry) {
            if ($entry->status === 'submitted') {
                $notifications->push($this->notification(
                    500000 + $entry->id,
                    'lna.awaiting_supervisor',
                    'info',
                    'LNA Awaiting Supervisor',
                    "Your {$entry->focus_area} assessment is waiting for supervisor evaluation.",
                    '/employee/learning-needs-analysis',
                    $entry->created_at,
                ));
            } elseif ($entry->status === 'returned') {
                $notifications->push($this->notification(
                    600000 + $entry->id,
                    'lna.returned',
                    'error',
                    'LNA Returned for Revision',
                    $entry->review_remarks ?: "Your {$entry->focus_area} assessment needs revision.",
                    '/employee/learning-needs-analysis',
                    $entry->reviewed_at ?? $entry->updated_at,
                ));
            } elseif ($entry->status === 'reviewed' && $entry->trainingApplications->isEmpty()) {
                $notifications->push($this->notification(
                    700000 + $entry->id,
                    'lna.reviewed',
                    'success',
                    'Supervisor Evaluation Complete',
                    "Your {$entry->focus_area} LNA was endorsed. You may now submit the recommended training application.",
                    '/employee/training-applications',
                    $entry->reviewed_at ?? $entry->updated_at,
                ));
            }
        }

        TrainingApplication::query()
            ->with('learningDevelopmentPlan')
            ->where('user_id', $employee->id)
            ->latest()
            ->get()
            ->each(function (TrainingApplication $application) use ($notifications) {
                [$title, $body, $type] = match (true) {
                    $application->status === 'ongoing' => [
                        'You Will Undergo Training',
                        "HRDC approved {$application->training_title}. You will undergo this training; please wait for the Secretariat's schedule and instructions.",
                        'success',
                    ],
                    $application->status === 'rejected' => [
                        'Training Program Disapproved',
                        $application->process_remarks
                            ?: "HRDC disapproved {$application->training_title}. Please review the decision and coordinate with the Secretariat if clarification is needed.",
                        'error',
                    ],
                    $application->secretariat_status === 'returned' => [
                        'Application Returned',
                        $application->process_remarks ?: "Secretariat returned {$application->training_title} for clarification.",
                        'error',
                    ],
                    $application->learningDevelopmentPlan?->status === 'submitted' => [
                        'Proposal Sent to HRDC',
                        "Secretariat submitted the L&D Plan for {$application->training_title} to HRDC.",
                        'info',
                    ],
                    $application->secretariat_status === 'processed' => [
                        'Application Processed',
                        "Secretariat processed {$application->training_title} and is preparing the L&D Plan.",
                        'info',
                    ],
                    default => [
                        'Application Sent to Secretariat',
                        "Your {$application->training_title} request is waiting for Secretariat processing.",
                        'warning',
                    ],
                };

                $notifications->push($this->notification(
                    800000 + $application->id,
                    'training_application.status',
                    $type,
                    $title,
                    $body,
                    "/employee/training-applications/{$application->id}",
                    $application->updated_at,
                ));
            });

        return $notifications->sortByDesc('timestamp')->take(20)->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function notification(
        int $id,
        string $event,
        string $type,
        string $title,
        string $body,
        string $url,
        mixed $occurredAt,
    ): array {
        return [
            'id' => $id,
            'event' => $event,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'time' => $occurredAt?->diffForHumans() ?? 'Recently',
            'timestamp' => $occurredAt?->getTimestamp() ?? 0,
            'url' => $url,
        ];
    }
}
