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

class UpdateRole
{
    /**
     * Only the keys present in `$data` are considered; values equal to the current ones are ignored.
     *
     * @param  array{name?: string, level?: int|null, color?: string|null, is_default?: bool}  $data
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function handle(User $actor, Role $role, array $data): Role
    {
        $gate = Gate::forUser($actor);
        $gate->authorize('update', $role);

        $changes = [];
        foreach (['name', 'level', 'color', 'is_default'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== $role->getAttribute($key)) {
                $changes[$key] = $data[$key];
            }
        }

        if (array_key_exists('name', $changes)) {
            if ($role->is_system) {
                throw new InvalidArgumentException('System role names cannot be changed.');
            }
            $gate->authorize('rename', $role);
        }

        if (array_key_exists('level', $changes)) {
            if ($role->is_system) {
                throw new InvalidArgumentException('System role levels cannot be changed.');
            }
            $gate->authorize('assignLevel', [Role::class, $changes['level'], $role]);
        }

        if (($changes['is_default'] ?? null) === false) {
            throw new InvalidArgumentException('Exactly one role must stay the default: set the flag on another role instead.');
        }

        if ($changes === []) {
            return $role;
        }

        DB::transaction(function () use ($role, $changes): void {
            if (($changes['is_default'] ?? false) === true) {
                Role::query()->whereKeyNot($role->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
            $role->update($changes);
        });

        Audit::log('role.updated', $actor, $role, ['changes' => $changes]);

        return $role;
    }
}
