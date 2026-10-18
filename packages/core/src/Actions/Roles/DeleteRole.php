<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Roles;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\RolePermissionGuard;

class DeleteRole
{
    /**
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function handle(User $actor, Role $role): void
    {
        Gate::forUser($actor)->authorize('delete', $role);

        RolePermissionGuard::authorize($actor, $role);

        if ($role->is_default) {
            throw new InvalidArgumentException('The default role cannot be deleted: make another role the default first.');
        }

        $snapshot = ['name' => $role->name, 'level' => $role->level, 'users' => $role->users()->count()];

        DB::transaction(function () use ($role): void {
            $role->users()->detach();
            $role->delete();
        });

        Audit::log('role.deleted', $actor, null, $snapshot);
    }
}
