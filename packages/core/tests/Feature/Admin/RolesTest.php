<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->mainAdmin = User::factory()->create()->assignRole('main_admin');
    $this->creator = User::factory()->create()->assignRole('creator');
    $this->superModerator = User::factory()->create()->assignRole('super_moderator')->givePermissionTo('roles.manage');
});

function rolesAs(User $actor): mixed
{
    return test()->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from('/admin/roles');
}

it('lists roles with counts', function () {
    rolesAs($this->mainAdmin)->get('/admin/roles')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/roles/index')
            ->has('roles', Role::query()->count())
            ->where('roles.0.name', 'vip')
            ->has('roles.0', fn (AssertableInertia $role) => $role
                ->hasAll(['id', 'name', 'level', 'color', 'is_default', 'is_system', 'users_count', 'permissions_count']))
            ->where('can.create', true));
});

it('forbids admins without roles.manage', function () {
    rolesAs($this->admin)->get('/admin/roles')->assertForbidden();
    rolesAs($this->admin)->post('/admin/roles', ['name' => 'editor'])->assertForbidden();
    rolesAs($this->admin)->get('/admin/roles/'.Role::findByName('user', 'web')->id.'/edit')->assertForbidden();
});

it('creates a role and redirects to its edit page', function () {
    $response = rolesAs($this->mainAdmin)->post('/admin/roles', ['name' => 'editor', 'color' => '#aabbcc']);

    $role = Role::findByName('editor', 'web');
    $response->assertRedirect("/admin/roles/{$role->id}/edit")->assertSessionHasNoErrors();
    expect($role->color)->toBe('#aabbcc')->and($role->is_system)->toBeFalse();
    expect(Activity::query()->where('event', 'role.created')->count())->toBe(1);
});

it('validates role input', function (array $payload, string $field) {
    rolesAs($this->mainAdmin)->post('/admin/roles', $payload)->assertSessionHasErrors($field);
})->with([
    'bad name' => [['name' => 'Bad Name'], 'name'],
    'digit first' => [['name' => '1role'], 'name'],
    'duplicate name' => [['name' => 'moderator'], 'name'],
    'level range' => [['name' => 'editor', 'level' => 7], 'level'],
    'taken level' => [['name' => 'editor', 'level' => 2], 'level'],
    'bad color' => [['name' => 'editor', 'color' => 'red'], 'color'],
]);

it('forbids creating a role above the actor level', function () {
    Role::query()->where('name', 'guest')->update(['level' => null]);
    $this->superModerator->givePermissionTo('roles.manage');

    // Свободный уровень выше ранга актора (super_moderator = 3): освободим 4.
    Role::query()->where('name', 'admin')->update(['level' => null]);

    rolesAs($this->superModerator)->post('/admin/roles', ['name' => 'editor', 'level' => 4])->assertForbidden();
});

it('updates a role and flashes a toast', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->mainAdmin)->put("/admin/roles/{$role->id}", ['name' => 'writer', 'color' => '#010203', 'level' => null, 'is_default' => false])
        ->assertRedirect('/admin/roles')
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect($role->fresh()->name)->toBe('writer');
});

it('rejects renaming a system role', function () {
    $moderator = Role::findByName('moderator', 'web');

    rolesAs($this->creator)->put("/admin/roles/{$moderator->id}", ['name' => 'mod'])
        ->assertSessionHasErrors('general');

    expect($moderator->fresh()->name)->toBe('moderator');
});

it('moves the default flag and refuses to clear the only default', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->mainAdmin)->put("/admin/roles/{$role->id}", ['is_default' => true])->assertSessionHasNoErrors();
    expect(Role::query()->where('is_default', true)->pluck('name')->all())->toBe(['editor']);

    rolesAs($this->mainAdmin)->put("/admin/roles/{$role->id}", ['is_default' => false])->assertSessionHasErrors('general');
    expect($role->fresh()->is_default)->toBeTrue();
});

it('deletes a custom role and detaches it from users', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $holder = User::factory()->create()->assignRole('user', 'editor');

    rolesAs($this->mainAdmin)->delete("/admin/roles/{$role->id}")->assertRedirect()->assertSessionHasNoErrors();

    expect(Role::query()->where('name', 'editor')->exists())->toBeFalse()
        ->and($holder->fresh()->getRoleNames()->all())->toBe(['user']);
});

it('cannot delete a system role', function () {
    rolesAs($this->creator)->delete('/admin/roles/'.Role::findByName('vip', 'web')->id)->assertForbidden();
});

it('cannot delete the default role', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web', 'is_default' => true]);
    Role::query()->where('name', 'user')->update(['is_default' => false]);

    rolesAs($this->mainAdmin)->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('general');
    expect($role->fresh())->not->toBeNull();
});

it('renders the permission matrix grouped by module with translated labels', function () {
    $role = Role::findByName('moderator', 'web');

    rolesAs($this->mainAdmin)->get("/admin/roles/{$role->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/roles/edit')
            ->where('role.name', 'moderator')
            ->has('permissions.users', 5)
            ->where('permissions.users.0', ['name' => 'users.assign-roles', 'label' => __('core::permissions.users.assign-roles')])
            ->where('permissions.roles', [['name' => 'roles.manage', 'label' => __('core::permissions.roles.manage')]])
            ->where('granted', fn ($granted) => collect($granted)->sort()->values()->all() === ['admin.access', 'complaints.review', 'users.ban', 'users.view'])
            ->where('can', ['update' => true, 'rename' => false, 'assignLevel' => false, 'delete' => false])
            ->has('grantable', 11));
});

it('limits grantable permissions to those the actor holds', function () {
    $role = Role::findByName('user', 'web');

    rolesAs($this->superModerator)->get("/admin/roles/{$role->id}/edit")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('grantable', fn ($grantable) => collect($grantable)->sort()->values()->all() === ['admin.access', 'complaints.review', 'roles.manage', 'users.ban', 'users.edit', 'users.view']));
});

it('syncs permissions through the endpoint', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->mainAdmin)->put("/admin/roles/{$role->id}/permissions", ['permissions' => ['users.view', 'users.edit']])
        ->assertRedirect('/admin/roles')
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['users.edit', 'users.view']);
});

it('rejects unknown permissions with a validation error', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->mainAdmin)->put("/admin/roles/{$role->id}/permissions", ['permissions' => ['nope']])
        ->assertSessionHasErrors('permissions.0');
});

it('forbids a delegated role manager escalating or revoking beyond their own permissions', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->superModerator)->put("/admin/roles/{$role->id}/permissions", ['permissions' => ['users.delete']])->assertForbidden();

    $role->givePermissionTo('users.delete');
    rolesAs($this->superModerator)->put("/admin/roles/{$role->id}/permissions", ['permissions' => []])->assertForbidden();
    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['users.delete']);
});

it('lets the creator grant anything', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->creator)->put("/admin/roles/{$role->id}/permissions", ['permissions' => ['users.delete', 'modules.manage']])
        ->assertSessionHasNoErrors();

    expect($role->fresh()->permissions)->toHaveCount(2);
});

it('keeps exactly one default when a form-encoded flag "1" is posted', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->mainAdmin)->put("/admin/roles/{$role->id}", ['is_default' => '1', 'level' => '', 'color' => ''])
        ->assertSessionHasNoErrors();

    expect(Role::query()->where('is_default', true)->pluck('name')->all())->toBe(['editor'])
        ->and($role->fresh()->level)->toBeNull()
        ->and($role->fresh()->color)->toBeNull();
});

it('refuses to clear the only default with a form-encoded "0"', function () {
    $default = Role::defaultForRegistration();

    rolesAs($this->creator)->put("/admin/roles/{$default->id}", ['is_default' => '0'])->assertSessionHasErrors('general');

    expect(Role::query()->where('is_default', true)->count())->toBe(1);
});

it('forbids a lower-ranked manager making a role with foreign permissions the default', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo('users.delete');

    rolesAs($this->superModerator)->put("/admin/roles/{$role->id}", ['is_default' => '1'])->assertForbidden();

    expect($role->fresh()->is_default)->toBeFalse();

    rolesAs($this->creator)->put("/admin/roles/{$role->id}", ['is_default' => '1'])->assertSessionHasNoErrors();
    expect($role->fresh()->is_default)->toBeTrue();
});

it('forbids updating and deleting a role at or above the actor level', function () {
    $admin = Role::findByName('admin', 'web');

    rolesAs($this->superModerator)->put("/admin/roles/{$admin->id}", ['color' => '#000000'])->assertForbidden();
    rolesAs($this->superModerator)->delete("/admin/roles/{$admin->id}")->assertForbidden();
});

it('forbids deleting a role carrying permissions the actor lacks', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo('users.delete');

    rolesAs($this->superModerator)->delete("/admin/roles/{$role->id}")->assertForbidden();

    expect($role->fresh())->not->toBeNull();
});

it('reports assignLevel ability for a custom level-less role', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    rolesAs($this->mainAdmin)->get("/admin/roles/{$role->id}/edit")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.assignLevel', true)->where('can.rename', true));
});

it('reports a privileged default role as a general error', function () {
    $moderator = Role::findByName('moderator', 'web');
    rolesAs($this->creator)->put("/admin/roles/{$moderator->id}", ['is_default' => '1'])->assertSessionHasErrors('general');
    expect($moderator->fresh()->is_default)->toBeFalse();

    // Уровни системных ролей уникальны — освобождаем 3 для новой роли.
    Role::query()->where('name', 'super_moderator')->update(['level' => null]);
    rolesAs($this->creator)->post('/admin/roles', ['name' => 'staff', 'level' => 3, 'is_default' => '1'])->assertSessionHasErrors('general');
    expect(Role::query()->where('name', 'staff')->exists())->toBeFalse();

    $default = Role::defaultForRegistration();
    rolesAs($this->creator)->put("/admin/roles/{$default->id}/permissions", ['permissions' => ['admin.access']])->assertSessionHasErrors('general');
    expect($default->fresh()->hasPermissionTo('admin.access'))->toBeFalse();
});
