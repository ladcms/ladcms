<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

/**
 * Единый источник правила «без эскалации через роль»: не-создатель может оперировать только
 * ролями, чьи permissions являются подмножеством его собственных.
 */
final class RolePermissionGuard
{
    /** @return list<string> */
    public static function permissionsOf(User $actor): array
    {
        return $actor->getAllPermissions()->pluck('name')->values()->all();
    }

    /** Все ли permissions роли есть у актора (создатель — всегда). */
    public static function covers(User $actor, Role $role): bool
    {
        if ($actor->isCreator()) {
            return true;
        }

        return array_diff($role->permissions->pluck('name')->all(), self::permissionsOf($actor)) === [];
    }

    /** @throws AuthorizationException */
    public static function authorize(User $actor, Role $role): void
    {
        if (! self::covers($actor, $role)) {
            throw new AuthorizationException('You cannot manage a role that carries permissions you do not have.');
        }
    }

    /** Роль, которую актор вправе выдавать пользователям: ниже его уровня и в рамках его permissions. */
    public static function mayAssign(User $actor, Role $role): bool
    {
        return $actor->isCreator()
            || (($role->level ?? 0) < $actor->level() && self::covers($actor, $role));
    }

    /**
     * Permissions, которыми актор вправе переключать у роли.
     *
     * @param  list<string>  $allNames
     * @return list<string>
     */
    public static function grantable(User $actor, array $allNames): array
    {
        return $actor->isCreator() ? $allNames : array_values(array_intersect($allNames, self::permissionsOf($actor)));
    }
}
