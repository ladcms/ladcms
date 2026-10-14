<?php

declare(strict_types=1);

namespace Ladcms\Core\Listeners;

use Illuminate\Auth\Events\Registered;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

/**
 * Назначает роль регистрации (`is_default`) новому пользователю без ролей.
 * Если роли нет (сайт не установлен), Role::defaultForRegistration() бросает ModelNotFoundException — намеренно.
 */
class AssignDefaultRole
{
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User && $event->user->roles()->count() === 0) {
            $event->user->assignRole(Role::defaultForRegistration());
        }
    }
}
