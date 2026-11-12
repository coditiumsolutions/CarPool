<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications()->latest('created_at')->latest('id');

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        return $this->paginated($query->paginate($this->perPage()));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok(['unread' => $request->user()->notifications()->where('is_read', false)->count()]);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        $notification->user_id === $request->user()->id || $this->deny('Not your notification.');
        $notification->update(['is_read' => true]);

        return $this->ok($notification, 'Marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $request->user()->notifications()->where('is_read', false)->update(['is_read' => true]);

        return $this->ok(['updated' => $count], 'All notifications marked as read.');
    }
}
