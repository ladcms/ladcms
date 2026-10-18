<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Support\UserModel;

class PruneUnverifiedUsers
{
    public function __construct(private readonly RegistrationSettings $registration) {}

    /**
     * Окончательно удаляет ни разу не подтверждённые аккаунты старше $days дней (без ролей выше базовой).
     * При отключённом подтверждении email ничего не делает: такие аккаунты — норма, а не мусор.
     */
    public function handle(int $days, int $chunk = 500): int
    {
        if (! $this->registration->require_email_verification) {
            return 0;
        }

        $count = 0;

        // lazyById: пагинация по id устойчива к удалению строк (offset-пагинация пропускала бы записи).
        $users = UserModel::query()->withTrashed()
            ->whereNull('email_verified_at')
            // Подтверждённый хоть раз аккаунт (потом сменивший email) — не мусор.
            ->whereNull('first_verified_at')
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
