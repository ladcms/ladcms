<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('web')->get('/probe', fn () => 'ok');
});

it('stamps last activity of an authenticated user', function () {
    $user = User::factory()->create(['last_activity_at' => null]);

    $this->actingAs($user)->get('/probe')->assertOk();

    expect($user->fresh()->last_activity_at)->not->toBeNull()
        ->and($user->fresh()->last_activity_at->diffInSeconds(now(), absolute: true))->toBeLessThan(2);
});

it('throttles updates to once a minute', function () {
    $user = User::factory()->create(['last_activity_at' => null]);
    $this->actingAs($user)->get('/probe');
    $first = $user->fresh()->last_activity_at;

    $this->travel(30)->seconds();
    $this->actingAs($user)->get('/probe');
    expect($user->fresh()->last_activity_at->equalTo($first))->toBeTrue();

    $this->travel(31)->seconds();
    $this->actingAs($user)->get('/probe');
    expect($user->fresh()->last_activity_at->greaterThan($first))->toBeTrue();
});

it('does not touch updated_at', function () {
    $user = User::factory()->create(['last_activity_at' => null]);
    $updatedAt = $user->fresh()->updated_at;

    $this->travel(5)->minutes();
    $this->actingAs($user)->get('/probe');

    expect($user->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('ignores guests', function () {
    $user = User::factory()->create(['last_activity_at' => null]);

    $this->get('/probe')->assertOk();

    expect($user->fresh()->last_activity_at)->toBeNull();
});

it('counts only recently active users as online', function () {
    $fresh = User::factory()->create(['last_activity_at' => now()->subMinutes(5)]);
    $stale = User::factory()->create(['last_activity_at' => now()->subMinutes(15)]);
    $never = User::factory()->create(['last_activity_at' => null]);

    expect(User::online()->count())->toBe(1)
        ->and(User::online()->first()?->id)->toBe($fresh->id)
        ->and($fresh->isOnline())->toBeTrue()
        ->and($stale->isOnline())->toBeFalse()
        ->and($never->isOnline())->toBeFalse();
});
