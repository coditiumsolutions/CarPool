<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(private readonly FcmService $fcm)
    {
    }

    /**
     * Store an in-app notification and push it via FCM.
     *
     * @param  array{booking_id?:int|null,ride_id?:int|null,request_id?:int|null,offer_id?:int|null}  $refs
     *         Records the notification points at, so the app can open the right screen.
     */
    public function notify(int $userId, string $title, string $message, string $type = 'general', array $refs = []): Notification
    {
        $refs = array_intersect_key($refs, array_flip(['booking_id', 'ride_id', 'request_id', 'offer_id']));

        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'notification_type' => $type,
            'is_read' => false,
        ] + $refs);

        // Push only after the surrounding DB transaction commits, and never let a push failure break the request.
        DB::afterCommit(function () use ($notification, $userId, $title, $message, $type, $refs) {
            try {
                $this->fcm->sendToUser($userId, $title, $message, ['notification_id' => $notification->id, 'type' => $type] + $refs);
            } catch (\Throwable $e) {
                Log::warning('Push notification failed: '.$e->getMessage());
            }
        });

        return $notification;
    }
}
