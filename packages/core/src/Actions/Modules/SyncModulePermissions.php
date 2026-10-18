<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

use Ladcms\Core\Models\Role;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\ModuleServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncModulePermissions
{
    /** Роли, которым выдаются все права модулей. */
    public const array FULL_ACCESS_ROLES = ['main_admin', 'creator'];

    /** @return list<string> созданные permissions */
    public function handle(ModuleServiceProvider $module): array
    {
        if (! $module instanceof HasPermissions) {
            return [];
        }

        $names = array_keys($module->permissions());
        $created = [];
        foreach ($names as $name) {
            if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
                $created[] = $name;
            }
        }

        // Высшие роли получают все права модулей (как '*' в PermissionsSeeder); выданное вручную не снимается.
        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', self::FULL_ACCESS_ROLES)->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($names);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
