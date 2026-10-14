<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('seeds the eight system roles with levels', function () {
    expect(Role::count())->toBe(8)
        ->and(Role::findByName('creator')->level)->toBe(6)
        ->and(Role::findByName('guest')->level)->toBe(0)
        ->and(Role::findByName('vip')->level)->toBeNull()
        ->and(Role::where('is_system', false)->count())->toBe(0)
        ->and(Role::defaultForRegistration()->name)->toBe('user');
});

it('is idempotent', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::count())->toBe(8);
});

it('computes user level and ranking', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $mod = User::factory()->create()->assignRole(['moderator', 'vip']);
    $nobody = User::factory()->create();

    expect($admin->level())->toBe(4)
        ->and($mod->level())->toBe(2)
        ->and($nobody->level())->toBe(0)
        ->and($admin->outranks($mod))->toBeTrue()
        ->and($mod->outranks($admin))->toBeFalse()
        ->and($mod->outranks($mod))->toBeFalse()
        ->and($admin->isCreator())->toBeFalse();
});

it('uses the custom role model through spatie', function () {
    $user = User::factory()->create()->assignRole('creator');

    expect($user->roles->first())->toBeInstanceOf(Role::class)
        ->and($user->isCreator())->toBeTrue();
});
