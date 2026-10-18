<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\RolePermissionGuard;
use Ladcms\Core\Support\UserModel;

class AssignRoles
{
    /**
     * Replaces the target's roles with the given ones.
     *
     * @param  list<string>  $roleNames
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function handle(User $actor, User $target, array $roleNames): User
    {
        $roleNames = array_values(array_unique($roleNames));

        // Инвариант проверяется до авторизации: последний создатель не может потерять роль creator.
        if (! in_array('creator', $roleNames, true) && $target->hasRole('creator') && UserModel::query()->role('creator')->count() <= 1) {
            throw new InvalidArgumentException('The last creator cannot lose the creator role.');
        }

        Gate::forUser($actor)->authorize('assignRoles', $target);
        $roles = Role::query()->with('permissions')->where('guard_name', 'web')->whereIn('name', $roleNames)->get();

        $missing = array_diff($roleNames, $roles->pluck('name')->all());
        if ($missing !== []) {
            throw ValidationException::withMessages(['roles' => 'Unknown roles: '.implode(', ', $missing).'.']);
        }

        // Проверяются только изменения: добавлять и снимать можно лишь роли, которые актор вправе назначать.
        // Роль вне его полномочий, присутствующая и до, и после, остаётся как есть.
        $current = Role::query()->with('permissions')->whereKey($target->roles()->pluck('roles.id')->all())->get();
        $added = $roles->reject(fn (Role $role): bool => $current->contains('id', $role->id));
        $removed = $current->reject(fn (Role $role): bool => $roles->contains('id', $role->id));

        $forbidden = $added->merge($removed)->reject(fn (Role $role): bool => RolePermissionGuard::mayAssign($actor, $role));

        if ($forbidden->isNotEmpty()) {
            throw new AuthorizationException('You cannot add or remove roles at or above your own level or with permissions you do not have.');
        }

        $previous = $current->pluck('name')->values()->all();
        $target->syncRoles($roles);
        Audit::log('roles.assigned', $actor, $target, ['roles' => $roles->pluck('name')->values()->all(), 'previous' => $previous]);

        return $target;
    }

    /**
     * Roles the actor may hand out: all for a creator; otherwise roles strictly below the actor's
     * level whose permissions are a subset of the actor's own (no escalation through a role).
     *
     * @return Collection<int, Role>
     */
    public static function assignableBy(User $actor): Collection
    {
        $roles = Role::query()->with('permissions')->where('guard_name', 'web')->orderBy('level')->orderBy('name')->get();

        return $roles->filter(fn (Role $role): bool => RolePermissionGuard::mayAssign($actor, $role))->values();
    }
}
