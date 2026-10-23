<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\LoginHistory;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\VerifiableUser\VerifiableUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['login' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.test']);
});

/** @return array<string, mixed> */
function profilePayload(User $user, array $override = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'sex' => null,
        'birth_date' => null,
        'about' => null,
        'signature' => null,
        'timezone' => 'UTC',
    ], $override);
}

it('redirects /settings to the profile tab and requires auth', function () {
    $this->getJson('/settings')->assertUnauthorized();
    $this->actingAs($this->user)->get('/settings')->assertRedirect(route('settings.profile'));
});

it('renders the profile settings page with the timezones and verification flag', function () {
    $this->actingAs($this->user)->get(route('settings.profile'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::settings/profile')
        ->where('profile.name', 'Alice')
        ->where('profile.login', 'alice')
        ->where('profile.email', 'alice@example.test')
        ->where('profile.email_verified', true)
        ->where('must_verify_email', false)
        ->has('timezones')
        ->where('timezones', fn ($zones) => in_array('Europe/Moscow', $zones->all(), true)));
});

it('exposes unverified state for MustVerifyEmail users', function () {
    $user = VerifiableUser::query()->findOrFail($this->user->id);
    $user->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($user)->get(route('settings.profile'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('must_verify_email', true)->where('profile.email_verified', false));
});

it('updates the profile and redirects back', function () {
    $this->actingAs($this->user)
        ->patch(route('settings.profile.update'), profilePayload($this->user, [
            'name' => 'Alice B', 'sex' => 'female', 'birth_date' => '1991-02-03', 'about' => 'Hi', 'signature' => 'Sig', 'timezone' => 'Europe/Moscow',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    $fresh = $this->user->fresh();
    expect($fresh->name)->toBe('Alice B')->and($fresh->about)->toBe('Hi')->and($fresh->timezone)->toBe('Europe/Moscow');
});

it('validates the profile payload', function (array $override, string $field) {
    $this->actingAs($this->user)
        ->patch(route('settings.profile.update'), profilePayload($this->user, $override))
        ->assertSessionHasErrors($field);
})->with([
    'empty name' => [['name' => ''], 'name'],
    'bad email' => [['email' => 'nope'], 'email'],
    'bad sex' => [['sex' => 'other'], 'sex'],
    'future birth date' => [['birth_date' => '2999-01-01'], 'birth_date'],
    'long about' => [['about' => str_repeat('x', 2001)], 'about'],
    'long signature' => [['signature' => str_repeat('x', 501)], 'signature'],
    'bad timezone' => [['timezone' => 'Mars/Base'], 'timezone'],
]);

it('rejects an email used by another user', function () {
    User::factory()->create(['email' => 'bob@example.test']);

    $this->actingAs($this->user)
        ->patch(route('settings.profile.update'), profilePayload($this->user, ['email' => 'BOB@example.test']))
        ->assertSessionHasErrors('email');
});

it('lowercases the email, resets verification and sends the verification mail', function () {
    Notification::fake();
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = true;
    $settings->save();
    $user = VerifiableUser::query()->findOrFail($this->user->id);

    $this->actingAs($user)
        ->patch(route('settings.profile.update'), profilePayload($user, ['email' => '  New@Example.COM ']))
        ->assertSessionHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh->email)->toBe('new@example.com')->and($fresh->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('returns 403 to a readonly-banned user changing name, about or signature (Review Focus 5)', function (array $override) {
    Ban::create(['user_id' => $this->user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Readonly, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    $this->actingAs($this->user)
        ->patch(route('settings.profile.update'), profilePayload($this->user, $override))
        ->assertForbidden();

    expect($this->user->fresh()->only(['name', 'about', 'signature']))->toBe(['name' => 'Alice', 'about' => null, 'signature' => null]);
})->with([
    'name' => [['name' => 'Hacked']],
    'about' => [['about' => 'Spam']],
    'signature' => [['signature' => 'Spam']],
]);

it('lets a readonly-banned user change timezone and email', function () {
    Ban::create(['user_id' => $this->user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Readonly, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    $this->actingAs($this->user)
        ->patch(route('settings.profile.update'), profilePayload($this->user, ['timezone' => 'Asia/Tokyo', 'email' => 'moved@example.test']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    expect($this->user->fresh()->timezone)->toBe('Asia/Tokyo')->and($this->user->fresh()->email)->toBe('moved@example.test');
});

it('changes the login through settings and keeps the history', function () {
    $this->actingAs($this->user)
        ->patch(route('settings.login.update'), ['login' => 'Alice_New'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    expect($this->user->fresh()->login)->toBe('alice_new')
        ->and(LoginHistory::query()->where('old_login', 'alice')->exists())->toBeTrue();
});

it('returns 403 to a readonly-banned user changing the login', function () {
    Ban::create(['user_id' => $this->user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Readonly, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    $this->actingAs($this->user)->patch(route('settings.login.update'), ['login' => 'new_login'])->assertForbidden();

    expect($this->user->fresh()->login)->toBe('alice');
});

it('rejects a taken or malformed login', function (string $login) {
    User::factory()->create(['login' => 'taken']);

    $this->actingAs($this->user)->patch(route('settings.login.update'), ['login' => $login])->assertSessionHasErrors('login');
})->with(['taken', 'x', 'bad login!']);

it('requires auth for every settings route', function () {
    $this->patchJson(route('settings.profile.update'), [])->assertUnauthorized();
    $this->patchJson(route('settings.login.update'), [])->assertUnauthorized();
    $this->getJson(route('settings.appearance'))->assertUnauthorized();
});

it('renders the appearance page', function () {
    $this->actingAs($this->user)->get(route('settings.appearance'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('core::settings/appearance'));
});

it('throttles profile updates to 6 per minute', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($this->user)->patch(route('settings.profile.update'), profilePayload($this->user))->assertRedirect();
    }

    $this->actingAs($this->user)->patch(route('settings.profile.update'), profilePayload($this->user))->assertStatus(429);
});

it('throttles login changes to 6 per minute', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($this->user)->patch(route('settings.login.update'), ['login' => '']);
    }

    $this->actingAs($this->user)->patch(route('settings.login.update'), ['login' => ''])->assertStatus(429);
});
