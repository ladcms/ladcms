<?php

declare(strict_types=1);

namespace Ladcms\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Support\RoleLevel;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    private const array COLORS = [
        'guest' => null, 'user' => null, 'moderator' => '#16a34a', 'super_moderator' => '#0d9488',
        'admin' => '#2563eb', 'main_admin' => '#7c3aed', 'creator' => '#dc2626',
    ];

    public function run(): void
    {
        foreach (RoleLevel::slugs() as $level => $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['level' => $level, 'color' => self::COLORS[$name], 'is_default' => $name === 'user', 'is_system' => true],
            );
        }

        Role::firstOrCreate(['name' => 'vip', 'guard_name' => 'web'], ['level' => null, 'color' => '#f59e0b', 'is_system' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
