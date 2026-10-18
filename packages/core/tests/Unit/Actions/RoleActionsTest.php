<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Roles\CreateRole;
use Ladcms\Core\Actions\Roles\DeleteRole;
use Ladcms\Core\Actions\Roles\SyncRolePermissions;
use Ladcms\Core\Actions\Roles\UpdateRole;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->creator = User::factory()->create()->assignRole('creator');
    $this->mainAdmin = User::factory()->create()->assignRole('main_admin');
    $this->admin = User::factory()->create()->assignRole('admin')->givePermissionTo('roles.manage');
    $this->superModerator = User::factory()->create()->assignRole('super_moderator')->givePermissionTo('roles.manage');
});

it('creates a role and audits it', function () {
    $role = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'editor', 'color' => '#112233']);

    expect($role->name)->toBe('editor')
        ->and($role->guard_name)->toBe('web')
        ->and($role->is_system)->toBeFalse()
        ->and($role->level)->toBeNull();

    $log = Activity::query()->where('event', 'role.created')->sole();
    expect($log->causer_id)->toBe($this->mainAdmin->id)->and($log->subject_id)->toBe($role->id);
});

it('forbids creating a role without roles.manage', function () {
    $plain = User::factory()->create()->assignRole('moderator');

    expect(fn () => app(CreateRole::class)->handle($plain, ['name' => 'editor']))->toThrow(AuthorizationException::class);
});

it('forbids creating a role at or above the actor level', function (int $level) {
    expect(fn () => app(CreateRole::class)->handle($this->admin, ['name' => 'editor', 'level' => $level]))
        ->toThrow(AuthorizationException::class);

    expect(Role::query()->where('name', 'editor')->exists())->toBeFalse();
})->with([4, 5, 6]);

it('lets an actor create a role strictly below their level', function () {
    Role::query()->where('name', 'guest')->update(['level' => null]);

    $role = app(CreateRole::class)->handle($this->admin, ['name' => 'editor', 'level' => 0]);

    expect($role->level)->toBe(0);
});

it('moves the default flag atomically on create and update', function () {
    $first = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'first', 'is_default' => true]);

    expect(Role::query()->where('is_default', true)->pluck('name')->all())->toBe(['first']);

    $second = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'second']);
    app(UpdateRole::class)->handle($this->mainAdmin, $second, ['is_default' => true]);

    expect(Role::query()->where('is_default', true)->pluck('name')->all())->toBe(['second'])
        ->and($first->fresh()->is_default)->toBeFalse();
});

it('does not allow clearing the flag from the only default role', function () {
    $default = Role::defaultForRegistration();

    expect(fn () => app(UpdateRole::class)->handle($this->creator, $default, ['is_default' => false]))
        ->toThrow(InvalidArgumentException::class);

    expect($default->fresh()->is_default)->toBeTrue();
});

it('updates name and color of a custom role and audits changed keys', function () {
    $role = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'editor']);

    app(UpdateRole::class)->handle($this->mainAdmin, $role, ['name' => 'writer', 'color' => '#ffffff']);

    expect($role->fresh()->name)->toBe('writer');
    $log = Activity::query()->where('event', 'role.updated')->sole();
    expect(array_keys($log->properties['changes']))->toEqualCanonicalizing(['name', 'color']);
});

it('rejects renaming or releveling a system role', function () {
    $moderator = Role::findByName('moderator', 'web');

    expect(fn () => app(UpdateRole::class)->handle($this->creator, $moderator, ['name' => 'mod']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(UpdateRole::class)->handle($this->creator, $moderator, ['level' => null]))
        ->toThrow(InvalidArgumentException::class);

    // Неизменённые значения и цвет разрешены.
    app(UpdateRole::class)->handle($this->creator, $moderator, ['name' => 'moderator', 'level' => 2, 'color' => '#000000']);
    expect($moderator->fresh()->color)->toBe('#000000');
});

it('forbids managing roles at or above the actor level', function () {
    $adminRole = Role::findByName('admin', 'web');

    expect(fn () => app(UpdateRole::class)->handle($this->admin, $adminRole, ['color' => '#000000']))
        ->toThrow(AuthorizationException::class);
});

it('forbids raising a custom role level to the actor level', function () {
    $role = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'editor']);

    expect(fn () => app(UpdateRole::class)->handle($this->admin, $role, ['level' => 4]))
        ->toThrow(AuthorizationException::class);
});

it('deletes a custom role, detaches it from users and audits', function () {
    $role = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'editor']);
    $holder = User::factory()->create()->assignRole('user', 'editor');

    app(DeleteRole::class)->handle($this->mainAdmin, $role);

    expect(Role::query()->where('name', 'editor')->exists())->toBeFalse()
        ->and($holder->fresh()->getRoleNames()->all())->toBe(['user'])
        ->and(Activity::query()->where('event', 'role.deleted')->count())->toBe(1);
});

it('refuses to delete system and default roles', function () {
    expect(fn () => app(DeleteRole::class)->handle($this->creator, Role::findByName('vip', 'web')))
        ->toThrow(AuthorizationException::class);

    $role = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'editor', 'is_default' => true]);

    expect(fn () => app(DeleteRole::class)->handle($this->mainAdmin, $role))->toThrow(InvalidArgumentException::class);
    expect($role->fresh())->not->toBeNull();
});

it('syncs permissions and audits added and removed', function () {
    $role = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'editor']);
    app(SyncRolePermissions::class)->handle($this->mainAdmin, $role, ['users.view', 'users.edit']);
    app(SyncRolePermissions::class)->handle($this->mainAdmin, $role, ['users.view', 'users.ban']);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['users.ban', 'users.view']);

    $log = Activity::query()->where('event', 'role.permissions_synced')->latest('id')->first();
    expect($log->properties['added'])->toBe(['users.ban'])->and($log->properties['removed'])->toBe(['users.edit']);
});

it('rejects unknown permission names', function () {
    $role = Role::findByName('vip', 'web');

    expect(fn () => app(SyncRolePermissions::class)->handle($this->creator, $role, ['nope.nothing']))
        ->toThrow(ValidationException::class);
});

it('forbids granting permissions the actor lacks', function () {
    $role = Role::findByName('user', 'web');

    expect(fn () => app(SyncRolePermissions::class)->handle($this->superModerator, $role, ['users.delete']))
        ->toThrow(AuthorizationException::class);

    expect($role->fresh()->permissions)->toHaveCount(0);
});

it('forbids revoking permissions the actor lacks but ignores unchanged ones', function () {
    $role = Role::findByName('vip', 'web');
    $role->givePermissionTo('users.delete');

    expect(fn () => app(SyncRolePermissions::class)->handle($this->superModerator, $role, []))
        ->toThrow(AuthorizationException::class);

    app(SyncRolePermissions::class)->handle($this->superModerator, $role, ['users.delete', 'users.view']);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['users.delete', 'users.view']);
});

it('lets the creator grant any permission', function () {
    $role = Role::findByName('vip', 'web');

    app(SyncRolePermissions::class)->handle($this->creator, $role, ['modules.manage', 'users.delete']);

    expect($role->fresh()->permissions)->toHaveCount(2);
});

it('forbids syncing permissions of a role at or above the actor level', function () {
    expect(fn () => app(SyncRolePermissions::class)->handle($this->superModerator, Role::findByName('admin', 'web'), ['users.view']))
        ->toThrow(AuthorizationException::class);
});

it('casts string flags and levels before diffing', function () {
    $role = app(CreateRole::class)->handle($this->mainAdmin, ['name' => 'editor']);

    app(UpdateRole::class)->handle($this->mainAdmin, $role, ['is_default' => '1', 'level' => '']);
    expect(Role::query()->where('is_default', true)->pluck('name')->all())->toBe(['editor']);

    expect(fn () => app(UpdateRole::class)->handle($this->mainAdmin, $role, ['is_default' => '0']))
        ->toThrow(InvalidArgumentException::class);
    expect(Role::query()->where('is_default', true)->count())->toBe(1);
});

it('forbids a non-creator making a role with foreign permissions the default', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo('users.delete');

    expect(fn () => app(UpdateRole::class)->handle($this->superModerator, $role, ['is_default' => true]))
        ->toThrow(AuthorizationException::class);

    app(UpdateRole::class)->handle($this->creator, $role, ['is_default' => true]);
    expect($role->fresh()->is_default)->toBeTrue();
});

it('forbids deleting a role with permissions the actor lacks', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo('users.delete');

    expect(fn () => app(DeleteRole::class)->handle($this->superModerator, $role))->toThrow(AuthorizationException::class);
    app(DeleteRole::class)->handle($this->creator, $role);
    expect(Role::query()->where('name', 'editor')->exists())->toBeFalse();
});

it('refuses to make a privileged role the default on update before writing anything', function () {
    $panel = Role::create(['name' => 'panel', 'guard_name' => 'web']);
    $panel->givePermissionTo('admin.access');
    $high = Role::findByName('moderator', 'web');
    $before = Role::query()->where('is_default', true)->pluck('name')->all();

    expect(fn () => app(UpdateRole::class)->handle($this->creator, $panel, ['is_default' => true]))
        ->toThrow(InvalidArgumentException::class, 'admin panel access')
        ->and(fn () => app(UpdateRole::class)->handle($this->creator, $high, ['is_default' => true]))
        ->toThrow(InvalidArgumentException::class, 'level above 1');

    expect(Role::query()->where('is_default', true)->pluck('name')->all())->toBe($before)
        ->and(Activity::query()->where('event', 'role.updated')->exists())->toBeFalse();
});

it('refuses to raise the level of the default role above 1', function () {
    $role = app(CreateRole::class)->handle($this->creator, ['name' => 'members', 'is_default' => true]);

    expect(fn () => app(UpdateRole::class)->handle($this->creator, $role, ['level' => 3]))
        ->toThrow(InvalidArgumentException::class, 'level above 1');
    expect($role->fresh()->level)->toBeNull();
});

it('refuses to create a default role with a level above 1', function () {
    $before = Role::query()->where('is_default', true)->pluck('name')->all();

    expect(fn () => app(CreateRole::class)->handle($this->creator, ['name' => 'staff', 'level' => 2, 'is_default' => true]))
        ->toThrow(InvalidArgumentException::class, 'level above 1');

    expect(Role::query()->where('name', 'staff')->exists())->toBeFalse()
        ->and(Role::query()->where('is_default', true)->pluck('name')->all())->toBe($before);
});

it('allows a default role with level up to 1 and without admin.access', function () {
    Role::query()->where('name', 'guest')->update(['level' => null]);
    $created = app(CreateRole::class)->handle($this->creator, ['name' => 'newbies', 'level' => 0, 'is_default' => true]);
    expect($created->fresh()->is_default)->toBeTrue();

    $plain = Role::create(['name' => 'plain', 'guard_name' => 'web']);
    $plain->givePermissionTo('users.view');
    app(UpdateRole::class)->handle($this->creator, $plain, ['is_default' => true]);
    expect($plain->fresh()->is_default)->toBeTrue();

    app(UpdateRole::class)->handle($this->creator, Role::findByName('user', 'web'), ['is_default' => true]);
    expect(Role::defaultForRegistration()->name)->toBe('user');
});

it('refuses to grant admin.access to the default role', function () {
    $default = Role::defaultForRegistration();

    expect(fn () => app(SyncRolePermissions::class)->handle($this->creator, $default, ['admin.access']))
        ->toThrow(InvalidArgumentException::class, 'admin panel access');
    expect($default->fresh()->hasPermissionTo('admin.access'))->toBeFalse();
});
