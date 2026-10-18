<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Auth\AuthenticateByLoginOrEmail;
use Ladcms\Core\Actions\Auth\RegisterUser;
use Ladcms\Core\Contracts\InviteValidator;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Http\Resources\CurrentUserResource;
use Ladcms\Core\Models\Ban;
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

/** Регистрация только по инвайтам; без валидатора — закрыта. */
function inviteOnly(): void
{
    $settings = app(RegistrationSettings::class);
    $settings->invite_only = true;
    $settings->save();
}

/** Фейковый валидатор: действителен только `GOOD` для new@example.com; запоминает погашенные коды. */
function fakeInviteValidator(): InviteValidator
{
    $validator = new class implements InviteValidator
    {
        /** @var list<array{string, int}> */
        public array $consumed = [];

        public function validate(string $code, string $email): bool
        {
            return $code === 'GOOD' && $email === 'new@example.com';
        }

        public function consume(string $code, User $user): void
        {
            $this->consumed[] = [$code, $user->id];
        }
    };

    app()->instance(InviteValidator::class, $validator);

    return $validator;
}

it('closes invite-only registration when no invite validator is bound', function () {
    inviteOnly();

    expect(fn () => app(RegisterUser::class)->handle(registrationInput(['invite_code' => 'GOOD'])))->toThrow(AuthorizationException::class)
        ->and(User::query()->count())->toBe(0);
});

it('requires a valid invite code and consumes it when registration is invite-only', function () {
    inviteOnly();
    $invites = fakeInviteValidator();

    expect(fn () => app(RegisterUser::class)->handle(registrationInput()))->toThrow(ValidationException::class)
        ->and(fn () => app(RegisterUser::class)->handle(registrationInput(['invite_code' => 'BAD'])))->toThrow(ValidationException::class)
        ->and($invites->consumed)->toBe([]);

    $user = app(RegisterUser::class)->handle(registrationInput(['invite_code' => 'GOOD']));

    expect($invites->consumed)->toBe([['GOOD', $user->id]]);
});

it('ignores invite codes when registration is not invite-only', function () {
    $invites = fakeInviteValidator();

    app(RegisterUser::class)->handle(registrationInput());

    expect($invites->consumed)->toBe([]);
});

it('leaves new users unverified when email verification is required', function () {
    expect(app(RegisterUser::class)->handle(registrationInput())->email_verified_at)->toBeNull();
});

it('auto-verifies new users when email verification is disabled', function () {
    $settings = app(RegistrationSettings::class);
    $settings->require_email_verification = false;
    $settings->save();

    expect(app(RegisterUser::class)->handle(registrationInput())->fresh()?->email_verified_at)->not->toBeNull();
});

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

    expect(array_keys($data))->toBe(['id', 'login', 'name', 'display_name', 'email', 'avatar_url', 'roles', 'permissions', 'level', 'is_creator', 'is_banned', 'can_write'])
        ->and($data['display_name'])->toBe('alice')
        ->and($data['level'])->toBe(2)
        ->and($data['is_creator'])->toBeFalse()
        ->and($data['roles'])->toBe(['moderator'])
        ->and($data['permissions'])->toContain('users.ban')
        ->and($data['is_banned'])->toBeFalse()
        ->and($data['can_write'])->toBeTrue();
});

it('reports creator flags and ban state for the frontend', function () {
    $this->seed(RolesSeeder::class);
    $creator = User::factory()->create()->assignRole('creator');
    $banned = User::factory()->create()->assignRole('user');
    Ban::query()->create(['user_id' => $banned->id, 'banned_by_id' => $creator->id, 'type' => BanType::Readonly,
        'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    $creatorData = CurrentUserResource::make($creator)->resolve();
    $bannedData = CurrentUserResource::make($banned)->resolve();

    expect($creatorData['level'])->toBe(6)
        ->and($creatorData['is_creator'])->toBeTrue()
        ->and($bannedData['is_banned'])->toBeTrue()
        ->and($bannedData['can_write'])->toBeFalse();
});

it('builds the current user with one roles query and one bans query', function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $user = User::factory()->create()->assignRole('admin');
    $user = User::query()->findOrFail($user->id);
    expect(app(RegistrationSettings::class)->readonly_hours)->toBe(0); // прогрев: настройки грузятся раз на запрос

    DB::enableQueryLog();
    CurrentUserResource::make($user)->resolve();
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    $rolesQueries = array_filter($queries, fn (string $q): bool => str_contains($q, 'from "roles"') && str_contains($q, 'model_has_roles'));
    $bansQueries = array_filter($queries, fn (string $q): bool => str_contains($q, 'from "bans"'));

    expect($rolesQueries)->toHaveCount(1)
        ->and($bansQueries)->toHaveCount(1)
        ->and(count($queries))->toBeLessThanOrEqual(4);
});
