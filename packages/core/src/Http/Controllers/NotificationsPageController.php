<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Actions\Notifications\MarkNotificationsRead;
use Ladcms\Core\Http\Resources\NotificationResource;
use Ladcms\Core\Models\User;

class NotificationsPageController
{
    private const int PER_PAGE = 20;

    public function index(Request $request, MarkNotificationsRead $markRead): Response
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $user->notifications()->reorder()->latest()->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        // Serialise first: the page must still show which items were unread when it was opened.
        $notifications = NotificationResource::collection($paginator)->response($request)->getData(true);

        $markRead->handle($user, $paginator->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all());

        return Inertia::render('core::notifications/index', ['notifications' => $notifications]);
    }
}
