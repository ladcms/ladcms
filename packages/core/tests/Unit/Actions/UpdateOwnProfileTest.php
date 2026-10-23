<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Users\UpdateOwnProfile;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\VerifiableUser\VerifiableUser;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.test', 'about' => 'Old about']);
});

function banReadonly(User $user): void
{
    Ban::create(['user_id' => $user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Readonly, 'reason_code' => 'spam', 'starts_at' => now()->subMinute(), 'expires_at' => null]);
}

it('updates own profile fields and logs the changed keys', function () {
    $user = app(UpdateOwnProfile::class)->handle($this->user, [
        'name' => 'New Name',
        'sex' => 'female',
        'birth_date' => '1990-05-01',
        'about' => 'About me',
        'signature' => 'Sig',
        'timezone' => 'Europe/Moscow',
        'login' => 'hacker',
        'is_suspicious' => true,
    ]);

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->sex)->toBe(Sex::Female)
        ->and($fresh->birth_date?->toDateString())->toBe('1990-05-01')
        ->and($fresh->about)->toBe('About me')
        ->and($fresh->signature)->toBe('Sig')
        ->and($fresh->timezone)->toBe('Europe/Moscow')
        ->and($fresh->login)->toBe($this->user->login)
        ->and($fresh->is_suspicious)->toBeFalse();

    $activity = Activity::query()->where('event', 'user.profile_updated')->sole();
    expect($activity->causer_id)->toBe($this->user->id)
        ->and($activity->subject_id)->toBe($this->user->id)
        ->and($activity->properties['changed'])->toEqualCanonicalizing(['name', 'sex', 'birth_date', 'about', 'signature', 'timezone']);
});

it('does not log when nothing changed', function () {
    app(UpdateOwnProfile::class)->handle($this->user, ['name' => 'Old', 'about' => 'Old about']);

    expect(Activity::query()->where('event', 'user.profile_updated')->exists())->toBeFalse();
});

it('rejects name, about and signature changes for a readonly user', function (string $field) {
    banReadonly($this->user);

    expect(fn () => app(UpdateOwnProfile::class)->handle($this->user, [$field => 'Changed']))
        ->toThrow(AuthorizationException::class);

    expect($this->user->fresh()->{$field})->not->toBe('Changed');
})->with(['name', 'about', 'signature']);

it('lets a readonly user save unchanged public fields together with timezone, sex and email', function () {
    banReadonly($this->user);

    app(UpdateOwnProfile::class)->handle($this->user, [
        'name' => 'Old', 'about' => 'Old about', 'signature' => null,
        'timezone' => 'Asia/Tokyo', 'sex' => 'male', 'email' => 'Fresh@Example.test',
    ]);

    $fresh = $this->user->fresh();
    expect($fresh->timezone)->toBe('Asia/Tokyo')->and($fresh->sex)->toBe(Sex::Male)->and($fresh->email)->toBe('fresh@example.test');
});

it('lowercases the email and resets verification', function () {
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = true;
    $settings->save();

    app(UpdateOwnProfile::class)->handle($this->user, ['email' => '  New@Example.TEST ']);

    $fresh = $this->user->fresh();
    expect($fresh->email)->toBe('new@example.test')->and($fresh->email_verified_at)->toBeNull();
});

it('marks the new email verified when the site does not require verification', function () {
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = false;
    $settings->save();

    app(UpdateOwnProfile::class)->handle($this->user, ['email' => 'new@example.test']);

    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
});

it('sends the verification mail on email change only for MustVerifyEmail users', function () {
    Notification::fake();
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = true;
    $settings->save();
    $verifiable = VerifiableUser::query()->findOrFail($this->user->id);

    app(UpdateOwnProfile::class)->handle($verifiable, ['name' => 'Old', 'email' => 'OLD@example.test']);
    Notification::assertNothingSent();

    app(UpdateOwnProfile::class)->handle($verifiable, ['email' => 'changed@example.test']);
    Notification::assertSentTo($verifiable, VerifyEmail::class);
    expect(Activity::query()->where('event', 'user.profile_updated')->sole()->properties['changed'])->toBe(['email']);
});

it('rejects an email taken by another user but allows the own one', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    expect(fn () => app(UpdateOwnProfile::class)->handle($this->user, ['email' => 'TAKEN@example.test']))
        ->toThrow(ValidationException::class);

    app(UpdateOwnProfile::class)->handle($this->user, ['email' => 'old@example.test', 'name' => 'Same email']);
    expect($this->user->fresh()->name)->toBe('Same email');
});

it('rejects an invalid timezone', function () {
    expect(fn () => app(UpdateOwnProfile::class)->handle($this->user, ['timezone' => 'Mars/Olympus']))
        ->toThrow(ValidationException::class);
});

it('rejects over-long about and signature', function () {
    expect(fn () => app(UpdateOwnProfile::class)->handle($this->user, ['about' => str_repeat('a', 2001)]))->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateOwnProfile::class)->handle($this->user, ['signature' => str_repeat('a', 501)]))->toThrow(ValidationException::class);
});

it('validates name, sex and birth date for direct callers', function (Closure $data) {
    expect(fn () => app(UpdateOwnProfile::class)->handle($this->user, $data()))->toThrow(ValidationException::class);
})->with([
    'empty name' => [fn () => ['name' => '']],
    'long name' => [fn () => ['name' => str_repeat('a', 256)]],
    'bad sex' => [fn () => ['sex' => 'other']],
    // Closure: the date is computed when the test runs, not at file load (midnight flake).
    'future birth date' => [fn () => ['birth_date' => now()->addDay()->toDateString()]],
    'bad birth date' => [fn () => ['birth_date' => 'yesterday-ish']],
]);

it('treats a case-only difference from a legacy mixed-case stored email as unchanged', function () {
    Notification::fake();
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = true;
    $settings->save();
    User::query()->whereKey($this->user->id)->toBase()->update(['email' => 'Foo@x.ru', 'email_verified_at' => now()]);
    $verifiable = VerifiableUser::query()->findOrFail($this->user->id);

    app(UpdateOwnProfile::class)->handle($verifiable, ['email' => 'foo@x.ru', 'name' => 'Old']);

    $fresh = $verifiable->fresh();
    expect($fresh->email)->toBe('Foo@x.ru')
        ->and($fresh->email_verified_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'user.profile_updated')->exists())->toBeFalse();
    Notification::assertNothingSent();
});
