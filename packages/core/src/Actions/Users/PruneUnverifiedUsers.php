<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Ladcms\Core\Models\User;

class PruneUnverifiedUsers
{
    /** Окончательно удаляет неподтверждённые аккаунты старше $days дней (без ролей выше базовой). */
    public function handle(int $days, int $chunk = 500): int
    {
        $count = 0;

        // lazyById: пагинация по id устойчива к удалению строк (offset-пагинация пропускала бы записи).
        $users = User::withTrashed()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDays($days))
            ->whereDoesntHave('roles', fn ($query) => $query->where('level', '>', 1))
            ->lazyById($chunk);

        foreach ($users as $user) {
            if ($user->forceDelete()) {
                $count++;
            }
        }

        return $count;
    }
}
