<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\Notification\FirebaseNotificationService;
use App\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FirebasePushChannel
{
    private FirebaseNotificationService $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send push notification via Firebase Cloud Messaging.
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!($notifiable instanceof User)) {
            return;
        }

        try {
            $data = method_exists($notification, 'toFirebase')
                ? $notification->toFirebase($notifiable)
                : (method_exists($notification, 'toArray') ? $notification->toArray($notifiable) : []);

            $title = $data['title'] ?? config('app.name', 'FUN MOMENT');
            $body = $data['order_message'] ?? $data['body'] ?? 'You have a new update.';

            $payload = [
                'type' => $data['notification_type'] ?? 'order_alert',
                'order_id' => (string) ($data['order_id'] ?? ''),
                'service_id' => (string) ($data['service_id'] ?? ''),
                'role' => (string) ($data['target_role'] ?? ''),
                'status' => (string) ($data['status'] ?? ''),
            ];

            $this->firebaseService->sendToUser($notifiable, $title, $body, $payload);
        } catch (\Throwable $e) {
            Log::error('[FirebasePushChannel] Failed dispatching push notification: ' . $e->getMessage());
        }
    }
}
