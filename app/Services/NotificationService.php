<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Store an in-app notification. FCM push will be added here later.
     */
    public function notify(int $userId, string $title, string $message, string $type = 'general'): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'notification_type' => $type,
            'is_read' => false,
        ]);
    }
}
