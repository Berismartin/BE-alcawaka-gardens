<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user's own notifications: no business rules, so no Action/Service layer. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->respond([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id,
                ...$n->data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->toISOString(),
            ]),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->respond(null, 'All caught up.');
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return $this->respond(null);
    }
}
