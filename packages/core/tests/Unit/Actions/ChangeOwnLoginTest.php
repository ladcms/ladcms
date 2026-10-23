<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Users\ChangeOwnLogin;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\LoginHistory;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

function readonlyBan(User $user): void
{
    Ban::create(['user_id' => $user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Readonly, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);
}

it('changes the login of a writable user via ChangeLogin', function () {
    $user = User::factory()->create(['login' => 'alice']);

    app(ChangeOwnLogin::class)->handle($user, 'Alice_New');

    expect($user->fresh()->login)->toBe('alice_new')
        ->and(LoginHistory::query()->where('old_login', 'alice')->exists())->toBeTrue();
});

it('forbids a readonly user to change the login', function () {
    $user = User::factory()->create(['login' => 'alice']);
    readonlyBan($user);

    expect(fn () => app(ChangeOwnLogin::class)->handle($user, 'other'))->toThrow(AuthorizationException::class);
    expect($user->fresh()->login)->toBe('alice');
});

it('is a no-op for an unchanged login even when readonly', function () {
    $user = User::factory()->create(['login' => 'alice']);
    readonlyBan($user);

    app(ChangeOwnLogin::class)->handle($user, ' ALICE ');

    expect($user->fresh()->login)->toBe('alice')->and(LoginHistory::query()->count())->toBe(0);
});
