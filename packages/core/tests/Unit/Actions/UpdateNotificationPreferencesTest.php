<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Users\UpdateNotificationPreferences;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\ComplaintFiled;
use Ladcms\Core\Notifications\UserBanned;
use Ladcms\Core\Notifications\UserMentioned;

uses(RefreshDatabase::class);

function ban(User $user): Ban
{
    return Ban::create(['user_id' => $user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subMinute(), 'expires_at' => null]);
}

it('defaults to database only, except mail for bans', function () {
    $user = User::factory()->create();

    expect($user->wantsNotification('mentions', 'database'))->toBeTrue()
        ->and($user->wantsNotification('mentions', 'mail'))->toBeFalse()
        ->and($user->wantsNotification('bans', 'mail'))->toBeTrue();
});

it('stores preferences and lets them drive via()', function () {
    $user = User::factory()->create();
    $mention = new UserMentioned($user, $user, '/x');

    expect($mention->via($user))->toBe(['database']);

    app(UpdateNotificationPreferences::class)->handle($user, ['mentions' => ['database' => true, 'mail' => true]]);
    expect($mention->via($user->fresh()))->toBe(['database', 'mail']);

    app(UpdateNotificationPreferences::class)->handle($user, ['mentions' => ['database' => false, 'mail' => false]]);
    expect($mention->via($user->fresh()))->toBe([]);
});

it('skips mail when the notifiable has no email', function () {
    $user = User::factory()->create();
    app(UpdateNotificationPreferences::class)->handle($user, ['complaints' => ['database' => true, 'mail' => true]]);
    $user->email = '';

    $complaint = new Complaint;
    expect(new ComplaintFiled($complaint)->via($user))->toBe(['database']);
});

it('forces bans.database to true', function () {
    $user = User::factory()->create();

    app(UpdateNotificationPreferences::class)->handle($user, ['bans' => ['database' => false, 'mail' => false]]);

    $fresh = $user->fresh();
    expect($fresh->notification_preferences['bans'])->toBe(['database' => true, 'mail' => false])
        ->and(new UserBanned(ban($fresh))->via($fresh))->toBe(['database']);
});

it('keeps untouched types and rejects unknown ones', function () {
    $user = User::factory()->create();
    app(UpdateNotificationPreferences::class)->handle($user, ['mentions' => ['database' => true, 'mail' => true]]);
    app(UpdateNotificationPreferences::class)->handle($user->fresh(), ['complaints' => ['database' => false, 'mail' => false]]);

    expect($user->fresh()->notification_preferences)->toHaveKeys(['mentions', 'complaints'])
        ->and(fn () => app(UpdateNotificationPreferences::class)->handle($user, ['spam' => ['database' => true, 'mail' => true]]))
        ->toThrow(ValidationException::class);
});
