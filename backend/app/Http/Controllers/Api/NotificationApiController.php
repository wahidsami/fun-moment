<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationApiController extends Controller
{
    /**
     * Register or refresh an FCM device token for the authenticated user.
     */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string|min:10',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unauthenticated'),
            ], 401);
        }

        $deviceType = $request->input('device_type', 'android');

        $tokenRecord = UserDeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_token' => $request->input('device_token'),
            ],
            [
                'device_type' => $deviceType,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Device token registered successfully'),
            'data' => [
                'id' => $tokenRecord->id,
                'device_type' => $tokenRecord->device_type,
                'last_used_at' => $tokenRecord->last_used_at,
            ],
        ]);
    }

    /**
     * Remove an FCM device token when user signs out or disables push notifications.
     */
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unauthenticated'),
            ], 401);
        }

        $deleted = UserDeviceToken::where('user_id', $user->id)
            ->where('device_token', $request->input('device_token'))
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Device token removed successfully'),
            'deleted' => (bool) $deleted,
        ]);
    }

    /**
     * Get paginated list of notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unauthenticated'),
            ], 401);
        }

        $perPage = min((int) $request->input('per_page', 15), 50);

        $notifications = $user->notifications()
            ->latest()
            ->paginate($perPage);

        $unreadCount = $user->unreadNotifications()->count();

        $items = $notifications->getCollection()->map(function ($item) {
            $data = is_array($item->data) ? $item->data : json_decode($item->data, true) ?? [];
            return [
                'id' => $item->id,
                'type' => class_basename($item->type),
                'order_id' => $data['order_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'order_message' => $data['order_message'] ?? '',
                'notification_type' => $data['notification_type'] ?? 'order_alert',
                'status' => $data['status'] ?? null,
                'target_role' => $data['target_role'] ?? null,
                'read_at' => $item->read_at ? $item->read_at->toIso8601String() : null,
                'is_read' => $item->read_at !== null,
                'created_at' => $item->created_at ? $item->created_at->toIso8601String() : null,
                'created_at_human' => $item->created_at ? $item->created_at->diffForHumans() : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'notifications' => [
                'current_page' => $notifications->currentPage(),
                'data' => $items,
                'first_page_url' => $notifications->url(1),
                'from' => $notifications->firstItem(),
                'last_page' => $notifications->lastPage(),
                'last_page_url' => $notifications->url($notifications->lastPage()),
                'next_page_url' => $notifications->nextPageUrl(),
                'path' => $notifications->path(),
                'per_page' => $notifications->perPage(),
                'prev_page_url' => $notifications->previousPageUrl(),
                'to' => $notifications->lastItem(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Get unread notifications count for badge display.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unauthenticated'),
            ], 401);
        }

        $count = $user->unreadNotifications()->count();

        return response()->json([
            'status' => 'success',
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unauthenticated'),
            ], 401);
        }

        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'status' => 'error',
                'message' => __('Notification not found'),
            ], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Notification marked as read'),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unauthenticated'),
            ], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => __('All notifications marked as read'),
            'unread_count' => 0,
        ]);
    }
}
