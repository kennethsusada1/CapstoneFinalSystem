<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $name = $request->user()?->name ?? 'Employee';

        return response()->json([
            [
                'id' => 1,
                'event' => 'development_plan.submitted',
                'type' => 'info',
                'title' => 'Development Plan Submitted',
                'body' => $name.' has a pending development plan update ready for review.',
                'time' => '2 mins ago',
                'url' => '/employee/my-idp',
                'is_read' => false,
            ],
            [
                'id' => 2,
                'event' => 'accomplishment.approved',
                'type' => 'success',
                'title' => 'Training Participation Approved',
                'body' => 'Your recent training participation has been acknowledged.',
                'time' => '1 hour ago',
                'url' => '/employee/history',
                'is_read' => true,
            ],
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        return response()->json(['id' => $id, 'ok' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
