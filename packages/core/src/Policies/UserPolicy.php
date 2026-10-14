<?php

declare(strict_types=1);

namespace Ladcms\Core\Policies;

use Ladcms\Core\Models\User;

/**
 * Admin abilities over users: permission (via Gate → spatie) plus strict rank check (`outranks`).
 *
 * `Gate::before` grants creators only plain permission checks (no model), so these policies run for
 * creators too: `can('<permission>')` passes for them, and only the model guards below can refuse.
 *
 * - `update`: self-edit is always allowed regardless of permissions; otherwise `users.edit` && outranks.
 * - `assignRoles`: strict `users.assign-roles` && outranks (creators cannot change other creators or themselves).
 * - `delete`: never self; `users.delete` && (outranks || actor is creator) && target is not a creator
 *   unless the actor is a creator too. "Not the last creator" is enforced in DeleteUser.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || $actor->can('users.view');
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return true;
        }

        return $actor->can('users.edit') && $actor->outranks($target);
    }

    public function ban(User $actor, User $target): bool
    {
        return $actor->can('users.ban') && $actor->outranks($target);
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->can('users.assign-roles') && $actor->outranks($target);
    }

    public function delete(User $actor, User $target): bool
    {
        if ($actor->is($target) || ! $actor->can('users.delete')) {
            return false;
        }

        if ($actor->isCreator()) {
            return true;
        }

        return $actor->outranks($target) && ! $target->isCreator();
    }
}
