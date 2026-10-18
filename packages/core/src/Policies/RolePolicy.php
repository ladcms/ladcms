<?php

declare(strict_types=1);

namespace Ladcms\Core\Policies;

use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

/**
 * Роли: `roles.manage` плюс ранг. Не-создатель управляет только ролями строго ниже своего уровня
 * (роль без уровня, например `vip`, считается уровнем 0) и не может поднять роль до своего уровня.
 * У системных ролей (`is_system`) имя и уровень неизменяемы, удалить их нельзя никому.
 *
 * Проверки с моделью/аргументами выполняются и для создателя (Gate::before его здесь не пропускает).
 *
 * - `rename`: `Gate::allows('rename', $role)`.
 * - `assignLevel`: `Gate::allows('assignLevel', [Role::class, $level])` при создании,
 *   `Gate::allows('assignLevel', [Role::class, $level, $role])` при изменении.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage') && $this->manageable($actor, $role);
    }

    public function rename(User $actor, Role $role): bool
    {
        return $this->update($actor, $role) && ! $role->is_system;
    }

    /** Новый уровень роли (`null` — без уровня); при изменении передаётся и сама роль. */
    public function assignLevel(User $actor, ?int $level, ?Role $role = null): bool
    {
        if (! $actor->can('roles.manage')) {
            return false;
        }

        if ($role instanceof Role) {
            if ($level === $role->level) {
                return $this->update($actor, $role);
            }

            if ($role->is_system || ! $this->manageable($actor, $role)) {
                return false;
            }
        }

        return $level === null || $level < $actor->level();
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage') && ! $role->is_system && $this->manageable($actor, $role);
    }

    private function manageable(User $actor, Role $role): bool
    {
        return $actor->isCreator() || ($role->level ?? 0) < $actor->level();
    }
}
