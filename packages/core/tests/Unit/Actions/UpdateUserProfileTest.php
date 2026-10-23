<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Users\PruneUnverifiedUsers;
use Ladcms\Core\Actions\Users\UpdateUserProfile;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\VerifiableUser\VerifiableUser;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->target = User::factory()->create(['name' => 'Old', 'email' => 'old@example.test'])->assignRole('user');
});

it('updates allowed profile fields and logs changed keys', function () {
    $user = app(UpdateUserProfile::class)->handle($this->admin, $this->target, [
        'name' => 'New Name',
        'email' => 'New@Example.TEST',
        'sex' => 'female',
        'birth_date' => '1990-05-01',
        'about' => 'About me',
        'signature' => 'Sig',
        'language' => 'en',
        'timezone' => 'Europe/Moscow',
        'login' => 'hacker',
        'is_suspicious' => true,
    ]);

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->email)->toBe('new@example.test')
        ->and($fresh->sex)->toBe(Sex::Female)
        ->and($fresh->birth_date?->toDateString())->toBe('1990-05-01')
        ->and($fresh->language)->toBe('en')
        ->and($fresh->timezone)->toBe('Europe/Moscow')
        ->and($fresh->login)->toBe($this->target->login)
        ->and($fresh->is_suspicious)->toBeFalse();

    $activity = Activity::query()->where('event', 'user.updated')->sole();
    expect($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->subject_id)->toBe($this->target->id)
        ->and($activity->properties['changed'])->toEqualCanonicalizing(['name', 'email', 'sex', 'birth_date', 'about', 'signature', 'language', 'timezone']);
});

it('does not log when nothing changed', function () {
    app(UpdateUserProfile::class)->handle($this->admin, $this->target, ['name' => 'Old']);

    expect(Activity::query()->where('event', 'user.updated')->exists())->toBeFalse();
});

it('lets users edit themselves', function () {
    app(UpdateUserProfile::class)->handle($this->target, $this->target, ['name' => 'Self']);

    expect($this->target->fresh()->name)->toBe('Self');
});

it('forbids editing equal or higher ranks and without users.edit', function () {
    $otherAdmin = User::factory()->create(['name' => 'Peer'])->assignRole('admin');
    $moderator = User::factory()->create()->assignRole('moderator');

    expect(fn () => app(UpdateUserProfile::class)->handle($this->admin, $otherAdmin, ['name' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateUserProfile::class)->handle($moderator, $this->target, ['name' => 'X']))->toThrow(AuthorizationException::class)
        ->and($otherAdmin->fresh()->name)->toBe('Peer')
        ->and($this->target->fresh()->name)->toBe('Old');
});

it('rejects an email taken by another user', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    expect(fn () => app(UpdateUserProfile::class)->handle($this->admin, $this->target, ['email' => 'TAKEN@example.test']))
        ->toThrow(ValidationException::class)
        ->and($this->target->fresh()->email)->toBe('old@example.test');
});

it('resets email verification when the email changes and verification is required', function () {
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = true;
    $settings->save();

    app(UpdateUserProfile::class)->handle($this->admin, $this->target, ['email' => 'changed@example.test']);

    expect($this->target->fresh()->email_verified_at)->toBeNull();
});

it('keeps the user verified on email change when verification is disabled', function () {
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = false;
    $settings->save();
    $this->target->forceFill(['email_verified_at' => null])->save();

    app(UpdateUserProfile::class)->handle($this->admin, $this->target, ['email' => 'changed@example.test']);

    expect($this->target->fresh()->email_verified_at)->not->toBeNull();
});

it('does not touch verification when the email is unchanged', function () {
    $verifiedAt = $this->target->email_verified_at;

    app(UpdateUserProfile::class)->handle($this->admin, $this->target, ['name' => 'Another', 'email' => 'OLD@example.test']);

    expect($this->target->fresh()->email_verified_at?->toIso8601String())->toBe($verifiedAt?->toIso8601String());
});

it('sends the verification mail when the email changes and verification is required', function () {
    Notification::fake();
    $target = VerifiableUser::query()->findOrFail($this->target->id);

    app(UpdateUserProfile::class)->handle($this->admin, $target, ['email' => 'changed@example.test']);

    Notification::assertSentTo($target, VerifyEmail::class);
    expect(Activity::query()->where('event', 'user.updated')->sole()->properties['changed'])->toBe(['email']);
});

it('does not send the verification mail when verification is disabled or the email is unchanged', function () {
    Notification::fake();
    $target = VerifiableUser::query()->findOrFail($this->target->id);

    app(UpdateUserProfile::class)->handle($this->admin, $target, ['name' => 'Another', 'email' => 'OLD@example.test']);

    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = false;
    $settings->save();
    app(UpdateUserProfile::class)->handle($this->admin, $target, ['email' => 'changed@example.test']);

    Notification::assertNothingSent();
});

it('keeps a once-verified user safe from the unverified prune after an email change', function () {
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = true;
    $settings->save();
    $this->target->forceFill(['created_at' => now()->subDays(30)])->save();
    $neverVerified = User::factory()->unverified()->create(['created_at' => now()->subDays(30)]);

    app(UpdateUserProfile::class)->handle($this->admin, $this->target, ['email' => 'changed@example.test']);

    expect($this->target->fresh()->email_verified_at)->toBeNull()
        ->and(app(PruneUnverifiedUsers::class)->handle(7))->toBe(1)
        ->and(User::withTrashed()->find($this->target->id))->not->toBeNull()
        ->and(User::withTrashed()->find($neverVerified->id))->toBeNull();
});

it('treats a case-only difference from a legacy mixed-case stored email as unchanged', function () {
    Notification::fake();
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = true;
    $settings->save();
    User::query()->whereKey($this->target->id)->toBase()->update(['email' => 'Foo@x.ru', 'email_verified_at' => now()]);
    $verifiable = VerifiableUser::query()->findOrFail($this->target->id);

    app(UpdateUserProfile::class)->handle($this->admin, $verifiable, ['email' => 'FOO@x.ru', 'name' => 'Old']);

    $fresh = $verifiable->fresh();
    expect($fresh->email)->toBe('Foo@x.ru')
        ->and($fresh->email_verified_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'user.updated')->exists())->toBeFalse();
    Notification::assertNothingSent();
});
