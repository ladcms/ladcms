<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Users\ChangeLogin;
use Ladcms\Core\Models\LoginHistory;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

it('changes login and records history', function () {
    $user = User::factory()->create(['login' => 'oldname']);

    $user = app(ChangeLogin::class)->handle($user, ' NewName ');

    expect($user->login)->toBe('newname')
        ->and(LoginHistory::where('user_id', $user->id)->value('old_login'))->toBe('oldname')
        ->and(User::findByLogin('oldname')?->id)->toBe($user->id)
        ->and(User::findByCurrentLogin('oldname'))->toBeNull();
});

it('rejects invalid or taken logins', function () {
    User::factory()->create(['login' => 'taken']);
    $user = User::factory()->create();

    expect(fn () => app(ChangeLogin::class)->handle($user, 'taken'))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeLogin::class)->handle($user, 'TAKEN'))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeLogin::class)->handle($user, 'bad name!'))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeLogin::class)->handle($user, 'ab'))->toThrow(ValidationException::class);
});

it('blocks reusing a released login for 30 days', function () {
    $first = User::factory()->create(['login' => 'released']);
    app(ChangeLogin::class)->handle($first, 'moved');
    $second = User::factory()->create();

    expect(fn () => app(ChangeLogin::class)->handle($second, 'released'))->toThrow(ValidationException::class);

    LoginHistory::query()->update(['changed_at' => now()->subDays(31)]);
    expect(app(ChangeLogin::class)->handle($second, 'released')->login)->toBe('released');
});

it('lets the owner take back their own old login', function () {
    $user = User::factory()->create(['login' => 'mine']);
    app(ChangeLogin::class)->handle($user, 'other');

    expect(app(ChangeLogin::class)->handle($user, 'mine')->login)->toBe('mine');
});

it('keeps a soft-deleted user login reserved', function () {
    User::factory()->create(['login' => 'ghost'])->delete();
    $user = User::factory()->create();

    expect(fn () => app(ChangeLogin::class)->handle($user, 'ghost'))->toThrow(ValidationException::class);
});

it('does nothing when the new login equals the current one', function () {
    $user = User::factory()->create(['login' => 'same']);

    expect(app(ChangeLogin::class)->handle($user, ' SAME ')->login)->toBe('same')
        ->and(LoginHistory::query()->count())->toBe(0);
});
