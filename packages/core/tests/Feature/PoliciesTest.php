<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Policies\UserPolicy;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

function userWithRole(string $role): User
{
    return User::factory()->create()->assignRole($role);
}

it('resolves user policy abilities by permission and rank', function (string $actorRole, string $ability, string $targetRole, bool $expected) {
    $actor = userWithRole($actorRole);
    $target = userWithRole($targetRole);

    expect(Gate::forUser($actor)->allows($ability, $target))->toBe($expected);
})->with([
    'moderator bans user' => ['moderator', 'ban', 'user', true],
    'moderator cannot ban moderator' => ['moderator', 'ban', 'moderator', false],
    'moderator cannot ban admin' => ['moderator', 'ban', 'admin', false],
    'admin assigns roles to moderator' => ['admin', 'assignRoles', 'moderator', true],
    'admin cannot assign roles to admin' => ['admin', 'assignRoles', 'admin', false],
    'admin cannot ban admin' => ['admin', 'ban', 'admin', false],
    'admin cannot delete admin' => ['admin', 'delete', 'admin', false],
    'admin deletes user' => ['admin', 'delete', 'user', true],
    'admin cannot delete creator' => ['admin', 'delete', 'creator', false],
    'main admin cannot delete creator' => ['main_admin', 'delete', 'creator', false],
    'super moderator updates user' => ['super_moderator', 'update', 'user', true],
    'moderator cannot update user (no users.edit)' => ['moderator', 'update', 'user', false],
    'user cannot update other user' => ['user', 'update', 'user', false],
    'moderator cannot delete user (no users.delete)' => ['moderator', 'delete', 'user', false],
    'moderator views any user' => ['moderator', 'view', 'user', true],
    'user cannot view other user in admin' => ['user', 'view', 'user', false],
    'creator bans admin' => ['creator', 'ban', 'main_admin', true],
    'creator deletes creator' => ['creator', 'delete', 'creator', true],
    'creator cannot assign roles to creator' => ['creator', 'assignRoles', 'creator', false],
]);

it('lets any user update and view themselves', function () {
    $user = userWithRole('user');

    expect(Gate::forUser($user)->allows('update', $user))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $user))->toBeTrue()
        ->and(Gate::forUser($user)->allows('ban', $user))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $user))->toBeFalse();
});

it('checks viewAny against users.view', function () {
    expect(Gate::forUser(userWithRole('moderator'))->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser(userWithRole('user'))->allows('viewAny', User::class))->toBeFalse();
});

it('applies the user policy to subclasses of the core user model', function () {
    $sub = new class extends User
    {
        protected $table = 'users';
    };
    $mod = userWithRole('moderator');
    $target = $sub->newQuery()->findOrFail(userWithRole('user')->id);

    expect(Gate::getPolicyFor($target))->toBeInstanceOf(UserPolicy::class)
        ->and(Gate::forUser($mod)->allows('ban', $target))->toBeTrue();
});

it('manages roles only with roles.manage and never deletes system roles', function () {
    $main = userWithRole('main_admin');
    $admin = userWithRole('admin');
    $vip = Role::findByName('vip');
    $custom = Role::create(['name' => 'editor', 'guard_name' => 'web', 'level' => null]);

    expect(Gate::forUser($main)->allows('viewAny', Role::class))->toBeTrue()
        ->and(Gate::forUser($main)->allows('create', Role::class))->toBeTrue()
        ->and(Gate::forUser($main)->allows('update', $custom))->toBeTrue()
        ->and(Gate::forUser($main)->allows('delete', $vip))->toBeFalse()
        ->and(Gate::forUser($main)->allows('delete', $custom))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewAny', Role::class))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $custom))->toBeFalse();
});

it('runs model policies for creators so system roles stay protected', function () {
    $creator = userWithRole('creator');
    $custom = Role::create(['name' => 'editor', 'guard_name' => 'web', 'level' => null]);

    expect(Gate::forUser($creator)->allows('delete', Role::findByName('vip')))->toBeFalse()
        ->and(Gate::forUser($creator)->allows('delete', $custom))->toBeTrue();
});

it('does not let a creator delete themselves', function () {
    $creator = userWithRole('creator');
    userWithRole('creator');

    expect(Gate::forUser($creator)->allows('delete', $creator))->toBeFalse();
});

it('keeps creator abilities over lower ranks under model policies', function (string $ability) {
    $creator = userWithRole('creator');

    expect(Gate::forUser($creator)->allows($ability, userWithRole('admin')))->toBeTrue();
})->with(['update', 'ban', 'assignRoles', 'delete']);

it('lets non-creators manage only roles below their own level', function () {
    $main = userWithRole('main_admin');
    $custom = Role::create(['name' => 'editor', 'guard_name' => 'web', 'level' => null]);

    expect(Gate::forUser($main)->allows('update', Role::findByName('admin')))->toBeTrue()
        ->and(Gate::forUser($main)->allows('update', $custom))->toBeTrue()
        ->and(Gate::forUser($main)->allows('update', Role::findByName('main_admin')))->toBeFalse()
        ->and(Gate::forUser($main)->allows('update', Role::findByName('creator')))->toBeFalse()
        ->and(Gate::forUser($main)->allows('rename', $custom))->toBeTrue()
        ->and(Gate::forUser($main)->allows('rename', Role::findByName('admin')))->toBeFalse();
});

it('keeps new and changed role levels below the actor level', function () {
    $main = userWithRole('main_admin');
    $custom = Role::create(['name' => 'editor', 'guard_name' => 'web', 'level' => null]);
    $gate = Gate::forUser($main);

    expect($gate->allows('assignLevel', [Role::class, null]))->toBeTrue()
        ->and($gate->allows('assignLevel', [Role::class, 4]))->toBeTrue()
        ->and($gate->allows('assignLevel', [Role::class, 5]))->toBeFalse()
        ->and($gate->allows('assignLevel', [Role::class, 6]))->toBeFalse()
        ->and($gate->allows('assignLevel', [Role::class, 3, $custom]))->toBeTrue()
        ->and($gate->allows('assignLevel', [Role::class, 5, $custom]))->toBeFalse()
        ->and(Gate::forUser(userWithRole('admin'))->allows('assignLevel', [Role::class, 1]))->toBeFalse();
});

it('keeps system role names and levels immutable, even for creators', function () {
    $creator = Gate::forUser(userWithRole('creator'));
    $admin = Role::findByName('admin');

    expect($creator->allows('update', Role::findByName('creator')))->toBeTrue()
        ->and($creator->allows('rename', $admin))->toBeFalse()
        ->and($creator->allows('assignLevel', [Role::class, 2, $admin]))->toBeFalse()
        ->and($creator->allows('assignLevel', [Role::class, 4, $admin]))->toBeTrue()
        ->and($creator->allows('assignLevel', [Role::class, 6]))->toBeFalse();
});
