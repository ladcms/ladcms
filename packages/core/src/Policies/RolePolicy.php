<?php

declare(strict_types=1);

namespace Ladcms\Core\Policies;

use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

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
        return $actor->can('roles.manage');
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage') && ! $role->is_system;
    }
}
