<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\ModuleServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncModulePermissions
{
    /** @return list<string> созданные permissions */
    public function handle(ModuleServiceProvider $module): array
    {
        if (! $module instanceof HasPermissions) {
            return [];
        }

        $created = [];
        foreach (array_keys($module->permissions()) as $name) {
            if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
                $created[] = $name;
            }
        }

        if ($created !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $created;
    }
}
