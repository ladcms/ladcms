<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Roles;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\DefaultRoleGuard;
use Ladcms\Core\Support\RolePermissionGuard;
use Spatie\Permission\Models\Permission;

class SyncRolePermissions
{
    /**
     * Replaces the role's permissions. A non-creator actor may only add or remove permissions
     * they hold themselves; permissions that stay as they are are not checked.
     *
     * @param  list<string>  $permissionNames
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function handle(User $actor, Role $role, array $permissionNames): Role
    {
        Gate::forUser($actor)->authorize('update', $role);

        $names = array_values(array_unique($permissionNames));
        $known = Permission::query()->where('guard_name', 'web')->whereIn('name', $names)->pluck('name')->all();
        $missing = array_diff($names, $known);
        if ($missing !== []) {
            throw ValidationException::withMessages(['permissions' => 'Unknown permissions: '.implode(', ', $missing).'.']);
        }

        if ($role->is_default) {
            DefaultRoleGuard::ensureSafe(null, $names);
        }

        $current = $role->permissions()->pluck('name')->all();
        $added = array_values(array_diff($names, $current));
        $removed = array_values(array_diff($current, $names));

        if (! $actor->isCreator()) {
            $held = RolePermissionGuard::permissionsOf($actor);
            if (array_diff([...$added, ...$removed], $held) !== []) {
                throw new AuthorizationException('You cannot grant or revoke permissions you do not have.');
            }
        }

        $role->syncPermissions($names);
        Audit::log('role.permissions_synced', $actor, $role, ['added' => $added, 'removed' => $removed]);

        return $role;
    }
}
