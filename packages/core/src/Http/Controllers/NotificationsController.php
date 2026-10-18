<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ladcms\Core\Http\Resources\NotificationResource;
use Ladcms\Core\Models\User;

/** JSON-only: уведомления текущего пользователя для колокольчика. */
class NotificationsController
{
    private const int LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => NotificationResource::collection(
                $user->notifications()->reorder()->latest()->limit(self::LIMIT)->get(),
            )->resolve($request),
        ]);
    }

    public function markRead(Request $request, string $id): Response
    {
        $this->user($request)->notifications()->findOrFail($id)->markAsRead();

        return response()->noContent();
    }

    public function markAllRead(Request $request): Response
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
