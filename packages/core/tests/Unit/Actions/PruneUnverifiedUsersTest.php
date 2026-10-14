<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Users\PruneUnverifiedUsers;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('deletes old unverified users', function () {
    $old = User::factory()->unverified()->create(['created_at' => now()->subDays(10)]);

    expect(app(PruneUnverifiedUsers::class)->handle(7))->toBe(1)
        ->and(User::withTrashed()->find($old->id))->toBeNull();
});

it('purges soft-deleted unverified users too', function () {
    $old = User::factory()->unverified()->create(['created_at' => now()->subDays(10)]);
    $old->delete();

    expect(app(PruneUnverifiedUsers::class)->handle(7))->toBe(1)
        ->and(User::withTrashed()->find($old->id))->toBeNull();
});

it('keeps fresh unverified users', function () {
    $fresh = User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);

    expect(app(PruneUnverifiedUsers::class)->handle(7))->toBe(0)
        ->and(User::find($fresh->id))->not->toBeNull();
});

it('keeps old verified users', function () {
    $verified = User::factory()->create(['created_at' => now()->subDays(30)]);

    expect(app(PruneUnverifiedUsers::class)->handle(7))->toBe(0)
        ->and(User::find($verified->id))->not->toBeNull();
});

it('keeps old unverified users with an elevated role', function () {
    $mod = User::factory()->unverified()->create(['created_at' => now()->subDays(30)])->assignRole('moderator');

    expect(app(PruneUnverifiedUsers::class)->handle(7))->toBe(0)
        ->and(User::find($mod->id))->not->toBeNull();
});

it('runs through the artisan command', function () {
    User::factory()->unverified()->create(['created_at' => now()->subDays(10)]);

    $this->artisan('ladcms:prune-unverified', ['--days' => 7])
        ->expectsOutput('Pruned 1 unverified users.')
        ->assertSuccessful();
});

it('deletes every match across several chunks', function () {
    User::factory()->unverified()->count(5)->create(['created_at' => now()->subDays(10)]);

    expect(app(PruneUnverifiedUsers::class)->handle(7, chunk: 2))->toBe(5)
        ->and(User::withTrashed()->count())->toBe(0);
});
