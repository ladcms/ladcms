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
        $level = ($data['level'] ?? '') === '' ? null : (int) $data['level'];
        $color = ($data['color'] ?? '') === '' ? null : $data['color'];

        $gate->authorize('create', Role::class);
        $gate->authorize('assignLevel', [Role::class, $level]);

        $isDefault = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $role = DB::transaction(function () use ($data, $level, $color, $isDefault): Role {
            if ($isDefault) {
                Role::query()->where('is_default', true)->lockForUpdate()->get();
                Role::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $role = new Role([
                'name' => $data['name'],
                'guard_name' => 'web',
                'level' => $level,
                'color' => $color,
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
