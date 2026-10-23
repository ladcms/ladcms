<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Notifications;

use Ladcms\Core\Models\User;

class MarkNotificationsRead
{
    /**
     * Marks the given notifications as read, scoped to the user's own unread ones.
     *
     * @param  list<string>  $ids
     * @return int number of notifications that were marked
     */
    public function handle(User $user, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $user->unreadNotifications()->whereKey($ids)->update(['read_at' => now()]);
    }
}
