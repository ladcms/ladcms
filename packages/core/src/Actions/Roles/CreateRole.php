<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Roles;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;

class CreateRole
{
    /**
     * @param  array{name: string, level?: int|null, color?: string|null, is_default?: bool}  $data
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, array $data): Role
    {
        $gate = Gate::forUser($actor);
        $level = $data['level'] ?? null;

        $gate->authorize('create', Role::class);
        $gate->authorize('assignLevel', [Role::class, $level]);

        $isDefault = (bool) ($data['is_default'] ?? false);

        $role = DB::transaction(function () use ($data, $level, $isDefault): Role {
            if ($isDefault) {
                Role::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $role = new Role([
                'name' => $data['name'],
                'guard_name' => 'web',
                'level' => $level,
                'color' => $data['color'] ?? null,
                'is_default' => $isDefault,
                'is_system' => false,
            ]);
            $role->save();

            return $role;
        });

        Audit::log('role.created', $actor, $role, ['name' => $role->name, 'level' => $role->level, 'is_default' => $role->is_default]);

        return $role;
    }
}
