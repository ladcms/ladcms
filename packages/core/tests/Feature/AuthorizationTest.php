<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::middleware(['web', 'auth', 'admin'])->get('/admin-probe', fn () => 'ok');
});

it('seeds all core permissions', function () {
    expect(Permission::count())->toBe(11)
        ->and(Permission::where('name', 'users.assign-roles')->exists())->toBeTrue();
});

it('maps permissions to roles', function () {
    $mod = User::factory()->create()->assignRole('moderator');
    $admin = User::factory()->create()->assignRole('admin');
    $main = User::factory()->create()->assignRole('main_admin');

    expect($mod->can('users.ban'))->toBeTrue()
        ->and($mod->can('users.edit'))->toBeFalse()
        ->and($admin->can('settings.manage'))->toBeTrue()
        ->and($admin->can('roles.manage'))->toBeFalse()
        ->and($main->can('modules.manage'))->toBeTrue();
});

it('lets the creator do anything', function () {
    $creator = User::factory()->create()->assignRole('creator');

    expect($creator->can('roles.manage'))->toBeTrue()
        ->and($creator->can('something.undefined'))->toBeTrue();
});

it('guards admin routes by admin.access', function () {
    $user = User::factory()->create()->assignRole('user');
    $mod = User::factory()->create()->assignRole('moderator');

    $this->get('/admin-probe')->assertRedirect('/login');
    $this->actingAs($user)->get('/admin-probe')->assertForbidden();
    $this->actingAs($user)->getJson('/admin-probe')->assertForbidden()->assertJsonPath('message', 'Forbidden.');
    $this->actingAs($mod)->get('/admin-probe')->assertOk();
});

it('is idempotent and keeps manually granted permissions', function () {
    Role::findByName('moderator')->givePermissionTo('settings.manage');
    $this->seed(PermissionsSeeder::class);

    expect(Role::findByName('moderator')->hasPermissionTo('settings.manage'))->toBeTrue()
        ->and(Permission::count())->toBe(11);
});
