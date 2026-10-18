<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
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

        if (! $actor->isCreator()) {
            $actorLevel = $actor->level();
            $tooHigh = $roles->filter(fn (Role $role): bool => ($role->level ?? 0) >= $actorLevel);

            if ($tooHigh->isNotEmpty()) {
                throw new AuthorizationException('You cannot assign roles at or above your own level.');
            }

            // Роль не должна давать прав, которых нет у самого актора (иначе — эскалация через роль без уровня/ниже уровня).
            $actorPermissions = $actor->getAllPermissions()->pluck('name')->all();
            $escalating = $roles->filter(fn (Role $role): bool => array_diff($role->permissions->pluck('name')->all(), $actorPermissions) !== []);

            if ($escalating->isNotEmpty()) {
                throw new AuthorizationException('You cannot assign roles with permissions you do not have.');
            }
        }

        $previous = $target->getRoleNames()->values()->all();
        $target->syncRoles($roles);
        Audit::log('roles.assigned', $actor, $target, ['roles' => $roles->pluck('name')->values()->all(), 'previous' => $previous]);

        return $target;
    }
}
