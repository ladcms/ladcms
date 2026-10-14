<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

class AssignRoles
{
    /**
     * Replaces the target's roles with the given ones.
     *
     * @param  list<string>  $roleNames
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $target, array $roleNames): User
    {
        Gate::forUser($actor)->authorize('assignRoles', $target);

        $roleNames = array_values(array_unique($roleNames));
        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', $roleNames)->get();

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
        }

        // TODO(Task 10): Audit::log role assignment (old → new roles).
        $target->syncRoles($roles);

        return $target;
    }
}
