<?php

declare(strict_types=1);

namespace Ladcms\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Создаёт permissions ядра и раздаёт их ролям. Идемпотентен и только добавляет
 * недостающие права — выданные администратором вручную не снимаются.
 */
class PermissionsSeeder extends Seeder
{
    /** @var array<string, list<string>|'*'> */
    private const array MATRIX = [
        'moderator' => ['admin.access', 'users.view', 'users.ban', 'complaints.review'],
        'super_moderator' => ['admin.access', 'users.view', 'users.ban', 'complaints.review', 'users.edit'],
        'admin' => ['admin.access', 'users.view', 'users.edit', 'users.ban', 'users.delete', 'users.assign-roles',
            'settings.manage', 'complaints.review', 'activity.view'],
        'main_admin' => '*',
        'creator' => '*',
    ];

    public function run(): void
    {
        $provider = app()->getProvider(CoreServiceProvider::class);
        assert($provider instanceof CoreServiceProvider);
        $all = array_keys($provider->permissions());

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::MATRIX as $role => $perms) {
            Role::findByName($role, 'web')->givePermissionTo($perms === '*' ? $all : $perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
