<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Moderation\BanUser;
use Ladcms\Core\Actions\Moderation\LiftBan;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->mod = User::factory()->create()->assignRole('moderator');
    $this->target = User::factory()->create()->assignRole('user');
});

it('bans a lower-ranked user', function () {
    $ban = app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(3), 'ads');

    expect($ban->isActive())->toBeTrue()
        ->and($this->target->fresh()->isBanned())->toBeTrue()
        ->and($this->target->fresh()->canWrite())->toBeFalse()
        ->and($ban->banned_by_id)->toBe($this->mod->id);
});

it('refuses equal or higher ranks and missing permission', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $otherMod = User::factory()->create()->assignRole('moderator');
    $plain = User::factory()->create()->assignRole('user');

    expect(fn () => app(BanUser::class)->handle($this->mod, $admin, BanType::Full, 'spam', now()->addDay()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(BanUser::class)->handle($this->mod, $otherMod, BanType::Full, 'spam', now()->addDay()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(BanUser::class)->handle($plain, $this->target, BanType::Full, 'spam', now()->addDay()))->toThrow(AuthorizationException::class);
});

it('validates reason code and duration against moderation settings', function () {
    expect(fn () => app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'unknown', now()->addDay()))->toThrow(ValidationException::class)
        ->and(fn () => app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(60)))->toThrow(ValidationException::class)
        ->and(fn () => app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', null))->toThrow(ValidationException::class);

    $permanent = app(BanUser::class)->handle($this->mod, $this->target, BanType::Readonly, 'rules', null);
    expect($permanent->expires_at)->toBeNull();
});

it('lifts a ban', function () {
    $ban = app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(3));
    app(LiftBan::class)->handle($this->mod, $ban);

    expect($this->target->fresh()->isBanned())->toBeFalse();
});

it('ignores expired bans', function () {
    app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(3));
    $this->travel(4)->days();

    expect($this->target->fresh()->isBanned())->toBeFalse();
});

it('rejects an expiry in the past', function () {
    expect(fn () => app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->subDays(3)))
        ->toThrow(ValidationException::class, 'Expiry must be in the future.');

    expect($this->target->fresh()->bans()->count())->toBe(0);
});

it('refuses to lift a ban of a soft-deleted higher-ranked user', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $ban = Ban::create(['user_id' => $admin->id, 'banned_by_id' => $admin->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subHour(), 'expires_at' => now()->addDay()]);
    $admin->delete();

    expect(fn () => app(LiftBan::class)->handle($this->mod, $ban))->toThrow(AuthorizationException::class);
    expect($ban->fresh()->isActive())->toBeTrue();
});

it('refuses to lift a ban whose user no longer exists', function () {
    $ban = Ban::create(['user_id' => $this->target->id, 'banned_by_id' => $this->mod->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subHour(), 'expires_at' => now()->addDay()]);
    $ban->user_id = 999_999;

    expect(fn () => app(LiftBan::class)->handle($this->mod, $ban))->toThrow(AuthorizationException::class);
});

it('leaves an already expired ban untouched when lifting', function () {
    $expiredAt = now()->subDay()->startOfSecond();
    $ban = Ban::create(['user_id' => $this->target->id, 'banned_by_id' => $this->mod->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subDays(3), 'expires_at' => $expiredAt]);

    app(LiftBan::class)->handle($this->mod, $ban);

    expect($ban->fresh()->expires_at->equalTo($expiredAt))->toBeTrue();
});
