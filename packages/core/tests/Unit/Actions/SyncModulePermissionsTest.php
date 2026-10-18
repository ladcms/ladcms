<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Modules\SyncModulePermissions;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('creates permissions declared by the module idempotently', function () {
    $module = $this->app->register(DummyServiceProvider::class);

    expect((new SyncModulePermissions)->handle($module))->toBe(['dummy.view'])
        ->and(Permission::where('name', 'dummy.view')->exists())->toBeTrue()
        ->and((new SyncModulePermissions)->handle($module))->toBe([]);
});

it('grants module permissions to main_admin and creator only', function () {
    $this->seed(RolesSeeder::class);
    $module = $this->app->register(DummyServiceProvider::class);

    (new SyncModulePermissions)->handle($module);
    (new SyncModulePermissions)->handle($module);

    expect(Role::findByName('main_admin')->hasPermissionTo('dummy.view'))->toBeTrue()
        ->and(Role::findByName('creator')->hasPermissionTo('dummy.view'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('dummy.view'))->toBeFalse()
        ->and(User::factory()->create()->assignRole('main_admin')->hasPermissionTo('dummy.view'))->toBeTrue();
});
