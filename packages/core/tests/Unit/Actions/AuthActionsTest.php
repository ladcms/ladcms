<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Auth\AuthenticateByLoginOrEmail;
use Ladcms\Core\Actions\Auth\RegisterUser;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Http\Resources\CurrentUserResource;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\RegistrationSettings;

uses(RefreshDatabase::class);

function closeRegistration(): void
{
    $settings = app(RegistrationSettings::class);
    $settings->open = false;
    $settings->save();
}

/** @return array<string, string> */
function registrationInput(array $overrides = []): array
{
    return [
        'login' => 'New_User',
        'name' => 'New User',
        'email' => 'New@Example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        ...$overrides,
    ];
}

it('authenticates by login, case-insensitively', function () {
    $user = User::factory()->create(['login' => 'alice', 'password' => 'secret-password']);

    expect(app(AuthenticateByLoginOrEmail::class)->handle(' Alice ', 'secret-password')?->id)->toBe($user->id);
});

it('authenticates by email, case-insensitively', function () {
    $user = User::factory()->create(['email' => 'alice@example.com', 'password' => 'secret-password']);

    expect(app(AuthenticateByLoginOrEmail::class)->handle('ALICE@example.com', 'secret-password')?->id)->toBe($user->id);
});

it('rejects a wrong password or unknown identifier', function () {
    User::factory()->create(['login' => 'alice', 'password' => 'secret-password']);

    expect(app(AuthenticateByLoginOrEmail::class)->handle('alice', 'wrong-password'))->toBeNull()
        ->and(app(AuthenticateByLoginOrEmail::class)->handle('nobody', 'secret-password'))->toBeNull();
});

it('does not authenticate soft-deleted users', function () {
    User::factory()->create(['login' => 'alice', 'password' => 'secret-password'])->delete();

    expect(app(AuthenticateByLoginOrEmail::class)->handle('alice', 'secret-password'))->toBeNull();
});

it('answers no faster than the timebox for unknown identifiers', function () {
    User::factory()->create(['login' => 'alice', 'password' => 'secret-password']);

    foreach (['nobody', 'nobody@example.com', ''] as $identifier) {
        $start = hrtime(true);
        $result = app(AuthenticateByLoginOrEmail::class)->handle($identifier, 'secret-password');
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        expect($result)->toBeNull()->and($elapsedMs)->toBeGreaterThanOrEqual(150);
    }
});

it('rehashes an outdated password hash on successful login', function () {
    config(['hashing.rehash_on_login' => true, 'hashing.bcrypt.rounds' => 10]);
    $user = User::factory()->create(['login' => 'alice']);
    $user->forceFill(['password' => Hash::make('secret-password', ['rounds' => 4])])->saveQuietly();
    expect(Hash::needsRehash($user->fresh()->getAuthPassword()))->toBeTrue();

    app(AuthenticateByLoginOrEmail::class)->handle('alice', 'secret-password');

    $hash = $user->fresh()->getAuthPassword();
    expect(Hash::needsRehash($hash))->toBeFalse()
        ->and(password_get_info($hash)['options']['cost'])->toBe(10)
        ->and(Hash::check('secret-password', $hash))->toBeTrue();
});

it('registers a user with normalized login and email', function () {
    $user = app(RegisterUser::class)->handle(registrationInput(), '10.0.0.1');

    expect($user->exists)->toBeTrue()
        ->and($user->login)->toBe('new_user')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->registered_ip)->toBe('10.0.0.1')
        ->and($user->language)->toBe('ru')
        ->and(app(AuthenticateByLoginOrEmail::class)->handle('new_user', 'secret-password')?->id)->toBe($user->id);
});

it('refuses registration when it is closed', function () {
    closeRegistration();

    app(RegisterUser::class)->handle(registrationInput());
})->throws(AuthorizationException::class);

it('rejects a duplicate login or email', function () {
    User::factory()->create(['login' => 'new_user', 'email' => 'other@example.com']);
    User::factory()->create(['login' => 'other', 'email' => 'taken@example.com']);

    expect(fn () => app(RegisterUser::class)->handle(registrationInput()))->toThrow(ValidationException::class)
        ->and(fn () => app(RegisterUser::class)->handle(registrationInput(['login' => 'fresh', 'email' => 'TAKEN@example.com'])))
        ->toThrow(ValidationException::class);
});

it('requires accepting the rules only when the setting is on', function () {
    $settings = app(RegistrationSettings::class);
    $settings->require_rules = true;
    $settings->save();

    expect(fn () => app(RegisterUser::class)->handle(registrationInput()))->toThrow(ValidationException::class)
        ->and(app(RegisterUser::class)->handle(registrationInput(['rules_accepted' => '1']))->exists)->toBeTrue();
});

it('assigns the default role on Registered', function () {
    $this->seed(RolesSeeder::class);
    $user = User::factory()->create();

    event(new Registered($user));
    event(new Registered($user));

    expect($user->fresh()->getRoleNames()->all())->toBe(['user']);
});

it('keeps existing roles on Registered', function () {
    $this->seed(RolesSeeder::class);
    $user = User::factory()->create()->assignRole('moderator');

    event(new Registered($user));

    expect($user->fresh()->getRoleNames()->all())->toBe(['moderator']);
});

it('hides the register route when registration is closed', function () {
    Route::middleware('web')->get('/register', fn () => 'form')->name('register');
    Route::middleware('web')->post('/register', fn () => 'created')->name('register.store');

    $this->get('/register')->assertOk();
    $this->post('/register')->assertOk();

    closeRegistration();

    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertForbidden();
});

it('exposes the current user for the frontend', function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $user = User::factory()->create(['login' => 'alice', 'name' => ''])->assignRole('moderator');

    $data = CurrentUserResource::make($user)->resolve();

    expect(array_keys($data))->toBe(['id', 'login', 'name', 'display_name', 'email', 'avatar_url', 'roles', 'permissions', 'is_banned', 'can_write'])
        ->and($data['display_name'])->toBe('alice')
        ->and($data['roles'])->toBe(['moderator'])
        ->and($data['permissions'])->toContain('users.ban')
        ->and($data['is_banned'])->toBeFalse()
        ->and($data['can_write'])->toBeTrue();
});
