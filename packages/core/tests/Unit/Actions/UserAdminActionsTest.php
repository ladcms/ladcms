<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Users\AssignRoles;
use Ladcms\Core\Actions\Users\DeleteUser;
use Ladcms\Core\Actions\Users\MarkSuspicious;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->creator = User::factory()->create()->assignRole('creator');
    $this->target = User::factory()->create()->assignRole('user');
});

it('lets admin assign roles below their level', function () {
    $user = app(AssignRoles::class)->handle($this->admin, $this->target, ['moderator', 'vip']);

    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['moderator', 'vip']);
});

it('forbids admin assigning roles at or above their level', function (string $role) {
    expect(fn () => app(AssignRoles::class)->handle($this->admin, $this->target, [$role]))
        ->toThrow(AuthorizationException::class);

    expect($this->target->fresh()->getRoleNames()->all())->toBe(['user']);
})->with(['admin', 'main_admin', 'creator']);

it('forbids assigning roles to equal or higher ranks and without permission', function () {
    $otherAdmin = User::factory()->create()->assignRole('admin');
    $mod = User::factory()->create()->assignRole('moderator');

    expect(fn () => app(AssignRoles::class)->handle($this->admin, $otherAdmin, ['user']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AssignRoles::class)->handle($mod, $this->target, ['user']))->toThrow(AuthorizationException::class);
});

it('rejects unknown role names', function () {
    expect(fn () => app(AssignRoles::class)->handle($this->admin, $this->target, ['nope']))
        ->toThrow(ValidationException::class);
});

it('lets the creator assign any role', function () {
    app(AssignRoles::class)->handle($this->creator, $this->target, ['creator', 'main_admin']);

    expect($this->target->fresh()->isCreator())->toBeTrue();
});

it('marks a user suspicious with a note and clears it', function () {
    $user = app(MarkSuspicious::class)->handle($this->admin, $this->target, true, 'multi-account');

    expect($user->fresh()->is_suspicious)->toBeTrue()
        ->and($user->fresh()->suspicious_note)->toBe('multi-account');

    app(MarkSuspicious::class)->handle($this->admin, $this->target, false, null);

    expect($this->target->fresh()->is_suspicious)->toBeFalse()
        ->and($this->target->fresh()->suspicious_note)->toBeNull();
});

it('refuses marking suspicious without users.edit or rank', function () {
    $mod = User::factory()->create()->assignRole('moderator');

    expect(fn () => app(MarkSuspicious::class)->handle($mod, $this->target, true, null))->toThrow(AuthorizationException::class)
        ->and(fn () => app(MarkSuspicious::class)->handle($this->admin, $this->creator, true, null))->toThrow(AuthorizationException::class);
});

it('soft deletes a user', function () {
    app(DeleteUser::class)->handle($this->admin, $this->target);

    expect(User::find($this->target->id))->toBeNull()
        ->and(User::withTrashed()->find($this->target->id)?->trashed())->toBeTrue();
});

it('refuses deleting without permission or rank', function () {
    $mod = User::factory()->create()->assignRole('moderator');
    User::factory()->create()->assignRole('creator'); // not the last creator, so rank decides

    expect(fn () => app(DeleteUser::class)->handle($mod, $this->target))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteUser::class)->handle($this->admin, $this->creator))->toThrow(AuthorizationException::class);
});

it('never deletes the last creator', function () {
    expect(fn () => app(DeleteUser::class)->handle($this->creator, $this->creator))->toThrow(InvalidArgumentException::class);
    expect($this->creator->fresh()->trashed())->toBeFalse();
});

it('lets a creator delete another creator when two exist', function () {
    $second = User::factory()->create()->assignRole('creator');

    app(DeleteUser::class)->handle($this->creator, $second);

    expect($second->fresh()->trashed())->toBeTrue()
        ->and(fn () => app(DeleteUser::class)->handle($this->creator, $this->creator))->toThrow(InvalidArgumentException::class);
});

it('does not let a creator delete themselves even when another creator exists', function () {
    User::factory()->create()->assignRole('creator');

    expect(fn () => app(DeleteUser::class)->handle($this->creator, $this->creator))->toThrow(AuthorizationException::class);
});

it('never lets the last creator lose the creator role', function () {
    expect(fn () => app(AssignRoles::class)->handle($this->creator, $this->creator, ['user']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(AssignRoles::class)->handle($this->admin, $this->creator, ['user']))->toThrow(InvalidArgumentException::class);

    expect($this->creator->fresh()->isCreator())->toBeTrue();
});

it('does not let creators change roles of other creators', function () {
    $second = User::factory()->create()->assignRole('creator');

    expect(fn () => app(AssignRoles::class)->handle($this->creator, $second, ['user']))->toThrow(AuthorizationException::class);
    expect($second->fresh()->isCreator())->toBeTrue();
});

it('forbids assigning a role that grants permissions the actor lacks', function () {
    $escalating = Role::create(['name' => 'helper', 'guard_name' => 'web'])->givePermissionTo('roles.manage');

    expect(fn () => app(AssignRoles::class)->handle($this->admin, $this->target, [$escalating->name]))
        ->toThrow(AuthorizationException::class);

    expect($this->target->fresh()->getRoleNames()->all())->toBe(['user']);
});

it('lets admin assign a role whose permissions they hold', function () {
    Role::create(['name' => 'helper', 'guard_name' => 'web'])->givePermissionTo('users.view');

    app(AssignRoles::class)->handle($this->admin, $this->target, ['helper']);

    expect($this->target->fresh()->getRoleNames()->all())->toBe(['helper']);
});

it('lets the creator assign roles with any permissions', function () {
    Role::create(['name' => 'helper', 'guard_name' => 'web'])->givePermissionTo('roles.manage');

    app(AssignRoles::class)->handle($this->creator, $this->target, ['helper']);

    expect($this->target->fresh()->hasPermissionTo('roles.manage'))->toBeTrue();
});

it('validates only role deltas: untouchable roles are kept, but cannot be removed', function () {
    $foreign = Permission::query()->whereNotIn('name', $this->admin->getAllPermissions()->pluck('name'))->firstOrFail();
    $moduleRole = Role::create(['name' => 'module_keeper', 'guard_name' => 'web']);
    $moduleRole->givePermissionTo($foreign);
    $this->target->assignRole('module_keeper');

    app(AssignRoles::class)->handle($this->admin, $this->target, ['user', 'module_keeper', 'moderator']);
    expect($this->target->fresh()->getRoleNames()->sort()->values()->all())->toBe(['moderator', 'module_keeper', 'user']);

    expect(fn () => app(AssignRoles::class)->handle($this->admin, $this->target->fresh(), ['user']))
        ->toThrow(AuthorizationException::class);
    expect($this->target->fresh()->getRoleNames()->sort()->values()->all())->toBe(['moderator', 'module_keeper', 'user']);
});
