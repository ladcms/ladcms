<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Ladcms\Core\Models\User;

class PruneUnverifiedUsers
{
    /** Окончательно удаляет неподтверждённые аккаунты старше $days дней (без ролей выше базовой). */
    public function handle(int $days): int
    {
        $count = 0;

        User::withTrashed()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDays($days))
            ->whereDoesntHave('roles', fn ($query) => $query->where('level', '>', 1))
            ->each(function (User $user) use (&$count): void {
                $user->forceDelete();
                $count++;
            });

        return $count;
    }
}
