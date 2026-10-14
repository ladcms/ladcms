<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Support\CoreBeforeSpatieProviderOrder;
use Spatie\Permission\Models\Permission;

uses(CoreBeforeSpatieProviderOrder::class, RefreshDatabase::class);

it('binds the custom role model when core registers before spatie', function () {
    $this->seed(RolesSeeder::class);

    $user = User::factory()->create()->assignRole('admin');

    expect($user->roles->first())->toBeInstanceOf(Role::class)
        ->and($user->level())->toBe(4)
        ->and(config('permission.models.permission'))->toBe(Permission::class);
});
