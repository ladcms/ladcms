# LADCMS Core: домен (план 2a) — план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Реализовать серверный домен `core`: пользователь с `login`, роли с уровнями (гибрид Spatie + `level`), permissions ядра, настройки сайта (spatie/laravel-settings), баны, жалобы, история логинов, онлайн-трекинг, аудит, уведомления и mentions, аватары, локаль, мастер `ladcms:install`, и подключить всё это к auth приложения (Fortify: вход по login/email, регистрация с `login` и настройками). Админка (UI) и публичные страницы — следующие планы.

**Architecture:** Вся доменная логика — в `packages/core` (`Ladcms\Core\…`): модель `User` в core, `App\Models\User` — тонкий наследник; Actions с единственным `handle()`; политики и Gate на Spatie-permissions + иерархия `roles.level`; настройки — типизированные классы `Settings`, регистрируемые модулями через `HasSettings`; Fortify остаётся в `app` (как в starter kit), но делегирует core-Actions. Тесты пакета — Pest + Testbench из корня монорепо (`vendor/bin/pest`), тесты приложения — в `app/` через Sail.

**Tech Stack:** Laravel 13, PHP 8.4, spatie/laravel-permission 8, spatie/laravel-settings 3.9, spatie/laravel-activitylog 5, intervention/image 3, Fortify (в app), Inertia 3, Pest 5, Testbench 11.

**Spec:** `docs/superpowers/specs/2026-10-14-ladcms-foundation-and-core-design.md` — §5 (домен, схема, роуты, permissions), §8 (граничные случаи), §4.3 (`HasSettings`, `HasPermissions`). Этот план реализует §5.1–5.4 кроме UI (§5.3 админ-страницы и публичные страницы — планы 2b/3).

## Global Constraints

- PHP `^8.4`, Laravel `^13.0`; namespace `Ladcms\Core\`; `declare(strict_types=1);` в каждом PHP-файле пакета (Rector проверяет в CI).
- Бизнес-логика только в `src/Actions/**` (один публичный `handle()`), каждый Action — с unit-тестом без HTTP (`packages/core/tests/Unit/Actions/**`).
- Роли: уровни `guest 0, user 1, moderator 2, super_moderator 3, admin 4, main_admin 5, creator 6`, роль `vip` без уровня; все 8 — `is_system = true`; `user` — `is_default = true`.
- Permissions core (точно): `admin.access, users.view, users.edit, users.ban, users.delete, users.assign-roles, roles.manage, settings.manage, modules.manage, complaints.review, activity.view`. Guard `web`.
- Роль→permissions по умолчанию: `moderator`: `admin.access, users.view, users.ban, complaints.review`; `super_moderator`: как moderator + `users.edit`; `admin`: всё кроме `roles.manage`, `modules.manage`; `main_admin`, `creator`: всё. `creator` дополнительно обходит все проверки через `Gate::before`.
- Таблицы и поля — ровно по спеке §5.2 (имена колонок ниже в задачах — обязательны, другие модули на них рассчитывают).
- URL/имена роутов core, вводимые здесь: `GET /banned` (`banned`), `GET /api/users/mention-search` (`api.users.mention-search`, middleware `auth`, `throttle:60,1`, группа `web`).
- Команды: `vendor/bin/pest` (корень), `vendor/bin/pint packages/core tests`, `vendor/bin/phpstan analyse --memory-limit=1G`, `vendor/bin/rector --dry-run`; приложение: `cd app && vendor/bin/sail pest`, `vendor/bin/sail npm run check && npm run types:check && npm run build`.
- Коммит после каждой задачи, Conventional Commits, trailer `Co-Authored-By: <модель-автор> <noreply@anthropic.com>`.
- Ничего не класть в `app/app/**`, что должно жить в core; в `app` — только Fortify-провайдер, `App\Models\User`, kit-страницы.

## Review Focus

1. Пользователь с уровнем 2 (moderator) пытается забанить admin (4) или равного себе — `BanUser` обязан отказать (`AuthorizationException`), даже если у него есть `users.ban`. — тест в Task 7.
2. `login` регистрируется в разном регистре (`Vadim` / `vadim`) и с пробелами — должен нормализоваться (`trim`, lower для сравнения) и быть уникальным без учёта регистра; старый логин другого пользователя нельзя занять 30 дней после смены. — тесты в Task 9 и Task 13.
3. Забаненный `full` пользователь шлёт POST/JSON-запрос, а не только GET — `CheckBan` должен отвечать 403 JSON для `expectsJson()` и редиректом на `/banned` иначе; истёкший бан не блокирует. — тест в Task 7.
4. `ladcms:install` запущен повторно на уже установленном сайте — не должен затирать роли/настройки и создавать второго админа; должен сказать «уже установлено» и выйти с кодом 1 без `--force`. — тест в Task 6.
5. Регистрация при `RegistrationSettings::$open = false` — POST `/register` должен отвечать 403 (не 500 и не «успех»), GET `/register` — 404. — тест в Task 13.

---

### Task 1: Модель `User` в core, расширение таблицы `users`, тестовая инфраструктура

**Files:**
- Create: `packages/core/src/Models/User.php`, `packages/core/src/Enums/Sex.php`, `packages/core/src/Enums/MessagesFrom.php`, `packages/core/database/migrations/2026_10_15_000001_add_ladcms_fields_to_users_table.php`, `packages/core/database/factories/UserFactory.php`, `packages/core/tests/Unit/Models/UserTest.php`
- Modify: `packages/core/composer.json` (autoload factories), `tests/TestCase.php` (корень), `packages/core/src/CoreServiceProvider.php`

**Interfaces:**
- Produces: `Ladcms\Core\Models\User` (Authenticatable, `Notifiable`, `HasRoles`, `SoftDeletes`, `HasFactory`): колонки ниже; аксессор `display_name` (= `name` или `login`); `scopeOnline()` (Task 10 уточнит); `UserFactory` (`login` уникальный, `name`, `email`, `password`).
- Produces: enum `Sex: string { case Male = 'male'; case Female = 'female'; }`, enum `MessagesFrom: string { All='all', Friends='friends', None='none' }`.
- TestCase: провайдеры `[Inertia, Permission, LaravelSettingsServiceProvider, CoreServiceProvider]`, `auth.providers.users.model = Ladcms\Core\Models\User`, Laravel-миграции (`users` и т.д.) загружаются.

- [ ] **Step 1: Тест (падающий)**

`packages/core/tests/Unit/Models/UserTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

it('creates a user with ladcms fields and defaults', function () {
    $user = User::factory()->create(['login' => 'vadim', 'name' => 'Vadim', 'sex' => Sex::Male]);

    expect($user->login)->toBe('vadim')
        ->and($user->sex)->toBe(Sex::Male)
        ->and($user->language)->toBe('ru')
        ->and($user->timezone)->toBe('UTC')
        ->and($user->is_suspicious)->toBeFalse()
        ->and($user->show_email)->toBeFalse()
        ->and($user->messages_from)->toBe(MessagesFrom::All)
        ->and($user->display_name)->toBe('Vadim');
});

it('falls back to login as display name', function () {
    $user = User::factory()->create(['login' => 'anna', 'name' => '']);

    expect($user->display_name)->toBe('anna');
});

it('enforces unique login', function () {
    User::factory()->create(['login' => 'dup']);

    expect(fn () => User::factory()->create(['login' => 'dup']))->toThrow(\Illuminate\Database\QueryException::class);
});

it('soft deletes users', function () {
    $user = User::factory()->create();
    $user->delete();

    expect(User::count())->toBe(0)->and(User::withTrashed()->count())->toBe(1);
});
```

- [ ] **Step 2: Запустить — FAIL** (`vendor/bin/pest packages/core/tests/Unit/Models` → класс `Ladcms\Core\Models\User` не найден).

- [ ] **Step 3: Enums и модель**

`packages/core/src/Enums/Sex.php`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Enums;

enum Sex: string
{
    case Male = 'male';
    case Female = 'female';
}
```
`MessagesFrom.php` аналогично: `All = 'all'`, `Friends = 'friends'`, `None = 'none'`.

`packages/core/src/Models/User.php`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Ladcms\Core\Database\Factories\UserFactory;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Enums\Sex;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $login
 * @property string $name
 * @property string $email
 * @property ?string $avatar_path
 * @property ?Sex $sex
 * @property ?\Illuminate\Support\Carbon $birth_date
 * @property ?string $about
 * @property ?string $signature
 * @property string $language
 * @property string $timezone
 * @property ?\Illuminate\Support\Carbon $last_activity_at
 * @property ?string $registered_ip
 * @property bool $is_suspicious
 * @property ?string $suspicious_note
 * @property bool $show_email
 * @property bool $show_birthday
 * @property MessagesFrom $messages_from
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'login', 'name', 'email', 'password', 'avatar_path', 'sex', 'birth_date', 'about', 'signature',
        'language', 'timezone', 'registered_ip', 'is_suspicious', 'suspicious_note',
        'show_email', 'show_birthday', 'messages_from',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sex' => Sex::class,
            'birth_date' => 'date',
            'last_activity_at' => 'datetime',
            'is_suspicious' => 'boolean',
            'show_email' => 'boolean',
            'show_birthday' => 'boolean',
            'messages_from' => MessagesFrom::class,
        ];
    }

    protected $attributes = [
        'language' => 'ru',
        'timezone' => 'UTC',
        'is_suspicious' => false,
        'show_email' => false,
        'show_birthday' => true,
        'messages_from' => 'all',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name !== '' ? $this->name : $this->login;
    }
}
```

- [ ] **Step 4: Миграция**

`2026_10_15_000001_add_ladcms_fields_to_users_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 32)->unique()->after('id');
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('sex', 8)->nullable()->after('avatar_path');
            $table->date('birth_date')->nullable()->after('sex');
            $table->text('about')->nullable()->after('birth_date');
            $table->text('signature')->nullable()->after('about');
            $table->string('language', 8)->default('ru')->after('signature');
            $table->string('timezone', 64)->default('UTC')->after('language');
            $table->timestamp('last_activity_at')->nullable()->index()->after('timezone');
            $table->string('registered_ip', 45)->nullable()->after('last_activity_at');
            $table->boolean('is_suspicious')->default(false)->after('registered_ip');
            $table->string('suspicious_note')->nullable()->after('is_suspicious');
            $table->boolean('show_email')->default(false)->after('suspicious_note');
            $table->boolean('show_birthday')->default(true)->after('show_email');
            $table->string('messages_from', 8)->default('all')->after('show_birthday');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'login', 'avatar_path', 'sex', 'birth_date', 'about', 'signature', 'language', 'timezone',
                'last_activity_at', 'registered_ip', 'is_suspicious', 'suspicious_note',
                'show_email', 'show_birthday', 'messages_from',
            ]);
        });
    }
};
```
Примечание: `two_factor_*` колонки добавляет приложение (kit); core их не создаёт, но прячет в `$hidden`.

- [ ] **Step 5: Фабрика и autoload**

`packages/core/database/factories/UserFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ladcms\Core\Models\User;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $login = strtolower($this->faker->unique()->userName());

        return [
            'login' => $login,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => str()->random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
```
В `packages/core/composer.json` → `autoload.psr-4` добавить `"Ladcms\\Core\\Database\\Factories\\": "database/factories/"` и в `require` → `"fakerphp/faker": "^1.24"` в `require-dev` корня (уже тянется Testbench — проверить `composer show fakerphp/faker`; если есть, ничего не добавлять). После правок — `composer update ladcms/core` в корне.

- [ ] **Step 6: TestCase корня**

`tests/TestCase.php` — заменить целиком:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Tests;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Models\User;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    use WithLaravelMigrations;

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            PermissionServiceProvider::class,
            LaravelSettingsServiceProvider::class,
            CoreServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('inertia.pages.ensure_pages_exist', false);
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }
}
```
`WithLaravelMigrations` подключает стандартные миграции Laravel (`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`) из Testbench-скелета; проверить в `vendor/orchestra/testbench-core/laravel/migrations`. Если имя трейта в установленной версии иное — найти `grep -rn "function loadLaravelMigrations" vendor/orchestra/testbench-core/src` и использовать `defineDatabaseMigrations(): void { $this->loadLaravelMigrations(); }`.

Важно для порядка: миграция core `…000001_add_ladcms_fields…` должна идти после `0001_01_01_000000_create_users_table` — таймстампы это гарантируют.

Ранее написанные тесты `ModulePageFinderTest` ставили `ensure_pages_exist=true` сами — оставить как есть (локальный override).

- [ ] **Step 7: Запустить — PASS.** `vendor/bin/pest` (весь корень; ожидается 42 + 4 = 46 passed). Pint, PHPStan, Rector.

- [ ] **Step 8: Commit** — `feat(core): User model with ladcms fields, factory and test harness`.

---

### Task 2: Роли с уровнями: модель `Role`, миграция, сидер, `outranks()`

**Files:**
- Create: `packages/core/src/Models/Role.php`, `packages/core/src/Support/RoleLevel.php`, `packages/core/database/migrations/2026_10_15_000002_add_level_fields_to_roles_table.php`, `packages/core/database/seeders/RolesSeeder.php`, `packages/core/tests/Unit/Models/RoleTest.php`
- Modify: `packages/core/src/Models/User.php`, `packages/core/src/CoreServiceProvider.php`, `packages/core/composer.json` (autoload seeders)

**Interfaces:**
- Produces: `Role extends Spatie\Permission\Models\Role` с `level: ?int`, `color: ?string`, `is_default: bool`, `is_system: bool`; статика `Role::defaultForRegistration(): Role`.
- Produces: `RoleLevel` — `final class` с константами `GUEST=0 … CREATOR=6` и `public static function slugs(): array<int,string>`.
- Produces: `User::level(): int` (максимальный `level` среди ролей, 0 если нет), `User::outranks(User $other): bool` (строго больше), `User::isCreator(): bool`.
- Produces: `RolesSeeder` (идемпотентный: `firstOrCreate` по `name`).

- [ ] **Step 1: Тест (падающий)**

`packages/core/tests/Unit/Models/RoleTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('seeds the eight system roles with levels', function () {
    expect(Role::count())->toBe(8)
        ->and(Role::findByName('creator')->level)->toBe(6)
        ->and(Role::findByName('guest')->level)->toBe(0)
        ->and(Role::findByName('vip')->level)->toBeNull()
        ->and(Role::where('is_system', false)->count())->toBe(0)
        ->and(Role::defaultForRegistration()->name)->toBe('user');
});

it('is idempotent', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::count())->toBe(8);
});

it('computes user level and ranking', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $mod = User::factory()->create()->assignRole(['moderator', 'vip']);
    $nobody = User::factory()->create();

    expect($admin->level())->toBe(4)
        ->and($mod->level())->toBe(2)
        ->and($nobody->level())->toBe(0)
        ->and($admin->outranks($mod))->toBeTrue()
        ->and($mod->outranks($admin))->toBeFalse()
        ->and($mod->outranks($mod))->toBeFalse()
        ->and($admin->isCreator())->toBeFalse();
});

it('uses the custom role model through spatie', function () {
    $user = User::factory()->create()->assignRole('creator');

    expect($user->roles->first())->toBeInstanceOf(Role::class)
        ->and($user->isCreator())->toBeTrue();
});
```

- [ ] **Step 2: Запустить — FAIL.**

- [ ] **Step 3: Миграция, модель, уровни**

Миграция `2026_10_15_000002_add_level_fields_to_roles_table.php`:
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->nullable()->unique()->after('guard_name');
            $table->string('color', 16)->nullable()->after('level');
            $table->boolean('is_default')->default(false)->after('color');
            $table->boolean('is_system')->default(false)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropColumn(['level', 'color', 'is_default', 'is_system']);
        });
    }
};
```
(с `declare(strict_types=1)` и импортами как в Task 1).

`packages/core/src/Support/RoleLevel.php`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

final class RoleLevel
{
    public const GUEST = 0;
    public const USER = 1;
    public const MODERATOR = 2;
    public const SUPER_MODERATOR = 3;
    public const ADMIN = 4;
    public const MAIN_ADMIN = 5;
    public const CREATOR = 6;

    /** @return array<int, string> level => role name */
    public static function slugs(): array
    {
        return [
            self::GUEST => 'guest',
            self::USER => 'user',
            self::MODERATOR => 'moderator',
            self::SUPER_MODERATOR => 'super_moderator',
            self::ADMIN => 'admin',
            self::MAIN_ADMIN => 'main_admin',
            self::CREATOR => 'creator',
        ];
    }
}
```

`packages/core/src/Models/Role.php`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property ?int $level
 * @property ?string $color
 * @property bool $is_default
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'level', 'color', 'is_default', 'is_system'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'is_default' => 'boolean', 'is_system' => 'boolean'];
    }

    public static function defaultForRegistration(): self
    {
        return static::query()->where('is_default', true)->firstOrFail();
    }
}
```

В `User` добавить:
```php
public function level(): int
{
    return (int) ($this->roles()->max('level') ?? 0);
}

public function outranks(User $other): bool
{
    return $this->level() > $other->level();
}

public function isCreator(): bool
{
    return $this->level() >= \Ladcms\Core\Support\RoleLevel::CREATOR;
}
```

- [ ] **Step 4: Сидер**

`packages/core/database/seeders/RolesSeeder.php`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Support\RoleLevel;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    private const COLORS = [
        'guest' => null, 'user' => null, 'moderator' => '#16a34a', 'super_moderator' => '#0d9488',
        'admin' => '#2563eb', 'main_admin' => '#7c3aed', 'creator' => '#dc2626',
    ];

    public function run(): void
    {
        foreach (RoleLevel::slugs() as $level => $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['level' => $level, 'color' => self::COLORS[$name], 'is_default' => $name === 'user', 'is_system' => true],
            );
        }

        Role::firstOrCreate(['name' => 'vip', 'guard_name' => 'web'], ['level' => null, 'color' => '#f59e0b', 'is_system' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```
`composer.json` core → autoload `"Ladcms\\Core\\Database\\Seeders\\": "database/seeders/"`; `composer update ladcms/core`.

- [ ] **Step 5: Подключить модель в Spatie**

В `CoreServiceProvider::registeringPackage()` (вызывается в `register()` до `PermissionServiceProvider::packageBooted`):
```php
public function registeringPackage(): void
{
    config(['permission.models.role' => Role::class]);
}
```
и в `packageBooted()`:
```php
$this->app->make(\Spatie\Permission\PermissionRegistrar::class)->setRoleClass(Role::class);
```
Проверить, что `PermissionRegistrar::setRoleClass` существует (cheat-sheet: `src/PermissionRegistrar.php:264`); если регистратор уже создан до нашего `config()`, именно `setRoleClass` переопределяет.

- [ ] **Step 6: Запустить — PASS.** Pint/PHPStan/Rector. **Commit** — `feat(core): roles with levels, seeder and ranking helpers`.

---

### Task 3: Permissions ядра, Gate, middleware `admin`

**Files:**
- Create: `packages/core/database/seeders/PermissionsSeeder.php`, `packages/core/tests/Feature/AuthorizationTest.php`
- Modify: `packages/core/src/CoreServiceProvider.php` (implements `HasPermissions`), `packages/core/src/Http/Middleware/AdminAccess.php`, `packages/core/lang/en/permissions.php`, `packages/core/lang/ru/permissions.php` (create)

**Interfaces:**
- Produces: `CoreServiceProvider::permissions()` — 11 permissions → ключи `core::permissions.<name>`; `PermissionsSeeder` создаёт их и назначает ролям по таблице Global Constraints (идемпотентно, `syncPermissions` только добавляет недостающие — не снимает выданные админом вручную).
- Produces: `Gate::before`: `creator` → `true` для любых abilities; middleware-алиасы `role`, `permission`, `role_or_permission` (Spatie) + `admin` = `AdminAccess` (проверяет `admin.access`, JSON 403 для `expectsJson()`).

- [ ] **Step 1: Тест (падающий)**

`packages/core/tests/Feature/AuthorizationTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::middleware(['web', 'auth', 'admin'])->get('/admin-probe', fn () => 'ok');
});

it('seeds all core permissions', function () {
    expect(Permission::count())->toBe(11)
        ->and(Permission::where('name', 'users.assign-roles')->exists())->toBeTrue();
});

it('maps permissions to roles', function () {
    $mod = User::factory()->create()->assignRole('moderator');
    $admin = User::factory()->create()->assignRole('admin');
    $main = User::factory()->create()->assignRole('main_admin');

    expect($mod->can('users.ban'))->toBeTrue()
        ->and($mod->can('users.edit'))->toBeFalse()
        ->and($admin->can('settings.manage'))->toBeTrue()
        ->and($admin->can('roles.manage'))->toBeFalse()
        ->and($main->can('modules.manage'))->toBeTrue();
});

it('lets the creator do anything', function () {
    $creator = User::factory()->create()->assignRole('creator');

    expect($creator->can('roles.manage'))->toBeTrue()
        ->and($creator->can('something.undefined'))->toBeTrue();
});

it('guards admin routes by admin.access', function () {
    $user = User::factory()->create()->assignRole('user');
    $mod = User::factory()->create()->assignRole('moderator');

    $this->get('/admin-probe')->assertRedirect('/login');
    $this->actingAs($user)->get('/admin-probe')->assertForbidden();
    $this->actingAs($user)->getJson('/admin-probe')->assertForbidden()->assertJsonPath('message', 'Forbidden.');
    $this->actingAs($mod)->get('/admin-probe')->assertOk();
});

it('is idempotent and keeps manually granted permissions', function () {
    \Ladcms\Core\Models\Role::findByName('moderator')->givePermissionTo('settings.manage');
    $this->seed(PermissionsSeeder::class);

    expect(\Ladcms\Core\Models\Role::findByName('moderator')->hasPermissionTo('settings.manage'))->toBeTrue()
        ->and(Permission::count())->toBe(11);
});
```
Примечание: редирект на `/login` требует роута `login` — в Testbench его нет; добавить в `beforeEach`: `Route::get('/login', fn () => 'login')->name('login');`.

- [ ] **Step 2: Запустить — FAIL.**

- [ ] **Step 3: Реализация**

`CoreServiceProvider`: `implements HasPermissions`:
```php
public function permissions(): array
{
    $names = ['admin.access', 'users.view', 'users.edit', 'users.ban', 'users.delete', 'users.assign-roles',
        'roles.manage', 'settings.manage', 'modules.manage', 'complaints.review', 'activity.view'];

    return array_combine($names, array_map(fn (string $n) => 'core::permissions.'.$n, $names));
}
```
`packages/core/lang/en/permissions.php`: массив `'admin.access' => 'Access the admin panel', …` (11 строк), `lang/ru/permissions.php` — русские подписи.

`PermissionsSeeder`:
```php
class PermissionsSeeder extends Seeder
{
    private const MATRIX = [
        'moderator' => ['admin.access', 'users.view', 'users.ban', 'complaints.review'],
        'super_moderator' => ['admin.access', 'users.view', 'users.ban', 'complaints.review', 'users.edit'],
        'admin' => ['admin.access', 'users.view', 'users.edit', 'users.ban', 'users.delete', 'users.assign-roles',
            'settings.manage', 'complaints.review', 'activity.view'],
        'main_admin' => '*',
        'creator' => '*',
    ];

    public function run(): void
    {
        $all = array_keys(app(CoreServiceProvider::class)->permissions());
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (self::MATRIX as $role => $perms) {
            Role::findByName($role, 'web')->givePermissionTo($perms === '*' ? $all : $perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```
`app(CoreServiceProvider::class)` — провайдер резолвится из контейнера как singleton? Нет: взять через `$this->container->getProvider(CoreServiceProvider::class)` → `app()->getProvider(...)`. Использовать `app()->getProvider(CoreServiceProvider::class)->permissions()`.

`CoreServiceProvider::packageBooted()` добавить:
```php
$router = $this->app['router'];
$router->aliasMiddleware('role', \Spatie\Permission\Middleware\RoleMiddleware::class);
$router->aliasMiddleware('permission', \Spatie\Permission\Middleware\PermissionMiddleware::class);
$router->aliasMiddleware('role_or_permission', \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class);

\Illuminate\Support\Facades\Gate::before(fn ($user, string $ability) => $user instanceof User && $user->isCreator() ? true : null);
```

`AdminAccess`:
```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if ($user === null) {
        throw new AuthenticationException;
    }
    if (! $user->can('admin.access')) {
        abort(403, 'Forbidden.');
    }

    return $next($request);
}
```
(`AuthenticationException` → редирект на `login` для web, 401 JSON для API — поведение Laravel по умолчанию; `abort(403)` для JSON даёт `{"message":"Forbidden."}`).

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): core permissions, seeder, gate and admin middleware`.

---

### Task 4: Настройки сайта (spatie/laravel-settings) и `HasSettings`

**Files:**
- Create: `packages/core/database/migrations/2026_10_15_000003_create_settings_table.php`, `packages/core/database/migrations/2026_10_15_000004_seed_core_settings.php`, `packages/core/src/Settings/{GeneralSettings,RegistrationSettings,ModerationSettings,AppearanceSettings}.php`, `packages/core/src/Module/Concerns/RegistersSettings.php`, `packages/core/tests/Feature/SettingsTest.php`
- Modify: `packages/core/src/Module/ModuleServiceProvider.php`, `packages/core/src/CoreServiceProvider.php` (implements `HasSettings`), `packages/core/tests/Fixtures/DummyModule/*` (добавить `DummySettings` + settings-миграцию для теста контракта)

**Interfaces:**
- Produces (поля — контракт для админки и модулей):
  - `GeneralSettings` (group `general`): `string $site_name`, `string $site_description`, `string $default_language`, `string $timezone`, `string $home_page`, `string $rules_markdown`.
  - `RegistrationSettings` (`registration`): `bool $open`, `bool $require_email_verification`, `bool $require_rules`, `bool $invite_only`, `int $readonly_hours`.
  - `ModerationSettings` (`moderation`): `array $violation_codes` (list of `['code' => string, 'label' => string, 'min_days' => int, 'max_days' => ?int]`), `int $password_confirm_minutes`.
  - `AppearanceSettings` (`appearance`): `string $accent_color`, `?string $logo_path`, `string $default_theme` (`system|light|dark`).
- Produces: `ModuleServiceProvider` регистрирует классы из `HasSettings::settings()` как `scoped` биндинги в `register()` (контейнер отдаёт экземпляр с данными из БД).

- [ ] **Step 1: Тест (падающий)**

`packages/core/tests/Feature/SettingsTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\Settings\DummySettings;

uses(RefreshDatabase::class);

it('seeds default core settings', function () {
    $general = app(GeneralSettings::class);
    $registration = app(RegistrationSettings::class);

    expect($general->site_name)->toBe('LADCMS')
        ->and($general->default_language)->toBe('ru')
        ->and($registration->open)->toBeTrue()
        ->and($registration->readonly_hours)->toBe(0);
});

it('persists updates', function () {
    $general = app(GeneralSettings::class);
    $general->site_name = 'My Site';
    $general->save();

    expect(app(GeneralSettings::class)->refresh()->site_name)->toBe('My Site');
});

it('registers settings classes declared by modules', function () {
    $this->app->register(DummyServiceProvider::class);
    $this->artisan('migrate')->assertSuccessful();

    expect(app(DummySettings::class)->greeting)->toBe('hello from settings');
});
```

- [ ] **Step 2: Запустить — FAIL.**

- [ ] **Step 3: Таблица и классы**

`2026_10_15_000003_create_settings_table.php` (из стаба spatie):
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

`GeneralSettings`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;
    public string $site_description;
    public string $default_language;
    public string $timezone;
    public string $home_page;
    public string $rules_markdown;

    public static function group(): string
    {
        return 'general';
    }
}
```
Остальные три — по полям из Interfaces.

`2026_10_15_000004_seed_core_settings.php`:
```php
<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'LADCMS');
        $this->migrator->add('general.site_description', '');
        $this->migrator->add('general.default_language', 'ru');
        $this->migrator->add('general.timezone', 'UTC');
        $this->migrator->add('general.home_page', 'home');
        $this->migrator->add('general.rules_markdown', '');

        $this->migrator->add('registration.open', true);
        $this->migrator->add('registration.require_email_verification', true);
        $this->migrator->add('registration.require_rules', false);
        $this->migrator->add('registration.invite_only', false);
        $this->migrator->add('registration.readonly_hours', 0);

        $this->migrator->add('moderation.violation_codes', [
            ['code' => 'spam', 'label' => 'Spam', 'min_days' => 1, 'max_days' => 30],
            ['code' => 'insult', 'label' => 'Insults', 'min_days' => 1, 'max_days' => 90],
            ['code' => 'rules', 'label' => 'Rules violation', 'min_days' => 1, 'max_days' => null],
        ]);
        $this->migrator->add('moderation.password_confirm_minutes', 30);

        $this->migrator->add('appearance.accent_color', '#0d9488');
        $this->migrator->add('appearance.logo_path', null);
        $this->migrator->add('appearance.default_theme', 'system');
    }
};
```

- [ ] **Step 4: Регистрация через `HasSettings`**

`packages/core/src/Module/Concerns/RegistersSettings.php`:
```php
<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Concerns;

use Ladcms\Core\Module\Contracts\HasSettings;

trait RegistersSettings
{
    protected function registerModuleSettings(): void
    {
        if (! $this instanceof HasSettings) {
            return;
        }

        foreach ($this->settings() as $class) {
            // spatie кэширует список классов статически при register() своего провайдера,
            // поэтому биндим сами — порядок провайдеров не важен.
            $this->app->scoped($class, fn () => new $class);
            config(['settings.settings' => array_values(array_unique([...config('settings.settings', []), $class]))]);
        }
    }
}
```
В `ModuleServiceProvider::register()` после `singletonIf(ModuleRegistry …)` и `parent::register()` вызвать `$this->registerModuleSettings()`; `use RegistersSettings;`.

`CoreServiceProvider implements HasSettings`: `settings(): array { return [GeneralSettings::class, RegistrationSettings::class, ModerationSettings::class, AppearanceSettings::class]; }`.

Фикстура: `tests/Fixtures/DummyModule/Settings/DummySettings.php` (`group 'dummy'`, `public string $greeting`), `DummyServiceProvider implements HasSettings` → `[DummySettings::class]`, миграция `tests/Fixtures/DummyModule/database/migrations/2026_10_15_000000_seed_dummy_settings.php` (`add('dummy.greeting', 'hello from settings')`).

Проверить: `new $class` без аргументов допустим (`Settings::__construct(array $values = [])` final) — да.

- [ ] **Step 5: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): site settings via spatie/laravel-settings and HasSettings registration`.

---

### Task 5: История логинов и `ChangeLogin`

**Files:**
- Create: `packages/core/database/migrations/2026_10_15_000005_create_login_history_table.php`, `packages/core/src/Models/LoginHistory.php`, `packages/core/src/Actions/Users/ChangeLogin.php`, `packages/core/src/Support/LoginRules.php`, `packages/core/tests/Unit/Actions/ChangeLoginTest.php`
- Modify: `packages/core/src/Models/User.php`

**Interfaces:**
- Produces: `login_history` (`user_id` FK, `old_login` string(32) index, `changed_at` timestamp); модель `LoginHistory`.
- Produces: `LoginRules::rules(?int $ignoreUserId = null): array` — `['required','string','min:3','max:32','regex:/^[a-z0-9_]+$/', Rule::unique(users,login)->ignore(...), new LoginNotRecentlyReleased]`; `LoginRules::normalize(string): string` (trim + lower).
- Produces: `ChangeLogin::handle(User $user, string $newLogin): User` — валидирует, пишет `login_history`, меняет `login`; `User::findByLogin(string): ?User` (учитывает историю: если логин в истории → возвращает текущего владельца); `User::findByCurrentLogin(string): ?User`.
- Правило: старый логин нельзя занять другому пользователю 30 дней (`LoginNotRecentlyReleased` rule).

- [ ] **Step 1: Тест (падающий)**

`packages/core/tests/Unit/Actions/ChangeLoginTest.php`:
```php
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
```

- [ ] **Step 2: FAIL.**

- [ ] **Step 3: Реализация**

Миграция `login_history`:
```php
Schema::create('login_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('old_login', 32)->index();
    $table->timestamp('changed_at');
});
```
Модель `LoginHistory` (`$table = 'login_history'`, `$timestamps = false`, fillable `user_id, old_login, changed_at`, cast `changed_at` datetime, `user()` belongsTo).

`packages/core/src/Rules/LoginNotRecentlyReleased.php` (`ValidationRule`): fails если `LoginHistory::where('old_login', $value)->where('changed_at', '>', now()->subDays(30))->where('user_id', '!=', $this->ignoreUserId)->exists()`; конструктор `(?int $ignoreUserId = null)`.

`LoginRules`:
```php
final class LoginRules
{
    public const REGEX = '/^[a-z0-9_]+$/';

    public static function normalize(string $login): string
    {
        return mb_strtolower(trim($login));
    }

    /** @return array<int, mixed> */
    public static function rules(?int $ignoreUserId = null): array
    {
        return ['required', 'string', 'min:3', 'max:32', 'regex:'.self::REGEX,
            Rule::unique('users', 'login')->ignore($ignoreUserId)->whereNull('deleted_at'),
            new LoginNotRecentlyReleased($ignoreUserId)];
    }
}
```
Уникальность без учёта регистра обеспечивается нормализацией (в БД всегда lower) + regex.

`ChangeLogin`:
```php
class ChangeLogin
{
    public function handle(User $user, string $newLogin): User
    {
        $login = LoginRules::normalize($newLogin);
        Validator::make(['login' => $login], ['login' => LoginRules::rules($user->id)])->validate();

        return DB::transaction(function () use ($user, $login) {
            LoginHistory::create(['user_id' => $user->id, 'old_login' => $user->login, 'changed_at' => now()]);
            $user->forceFill(['login' => $login])->save();

            return $user;
        });
    }
}
```
`User`:
```php
public static function findByCurrentLogin(string $login): ?self
{
    return static::query()->where('login', LoginRules::normalize($login))->first();
}

public static function findByLogin(string $login): ?self
{
    $login = LoginRules::normalize($login);

    return static::findByCurrentLogin($login)
        ?? LoginHistory::query()->where('old_login', $login)->latest('changed_at')->first()?->user;
}
```

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): login change with history and reuse protection`.

---

### Task 6: Мастер установки `ladcms:install` (без аргумента)

**Files:**
- Create: `packages/core/src/Actions/Install/InstallSite.php`, `packages/core/src/Actions/Install/CreateAdministrator.php`, `packages/core/tests/Unit/Actions/InstallSiteTest.php`, `packages/core/tests/Feature/InstallSiteCommandTest.php`
- Modify: `packages/core/src/Console/InstallModuleCommand.php` (аргумент становится опциональным; без него — мастер), `packages/core/src/CoreServiceProvider.php`

**Interfaces:**
- Produces: `InstallSite::handle(SiteInstallData $data, bool $force = false): User` — `migrate --force`, сидеры `RolesSeeder` + `PermissionsSeeder`, `GeneralSettings` (site_name, default_language), `CreateAdministrator`, запись `modules` для `core`; если `modules` уже содержит `core` и `!$force` → `SiteAlreadyInstalledException`.
- Produces: `SiteInstallData` (readonly DTO): `siteName`, `language`, `adminLogin`, `adminName`, `adminEmail`, `adminPassword`.
- Produces: `CreateAdministrator::handle(string $login, string $name, string $email, string $password): User` — создаёт пользователя с ролью `creator`, `email_verified_at = now()`.
- Команда: `ladcms:install [module] {--site-name=} {--language=ru} {--admin-login=} {--admin-name=} {--admin-email=} {--admin-password=} {--force}`; без `module` — мастер (интерактивные prompts через `Laravel\Prompts` если опции не заданы).

- [ ] **Step 1: Тесты (падающие)**

`InstallSiteTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Install\InstallSite;
use Ladcms\Core\Actions\Install\SiteAlreadyInstalledException;
use Ladcms\Core\Actions\Install\SiteInstallData;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Settings\GeneralSettings;

uses(RefreshDatabase::class);

function installData(): SiteInstallData
{
    return new SiteInstallData('My Community', 'en', 'owner', 'Owner', 'owner@example.com', 'secret-password-123');
}

it('installs roles, permissions, settings and the creator account', function () {
    $admin = app(InstallSite::class)->handle(installData());

    expect(Role::count())->toBe(8)
        ->and($admin->hasRole('creator'))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(app(GeneralSettings::class)->site_name)->toBe('My Community')
        ->and(app(GeneralSettings::class)->default_language)->toBe('en')
        ->and(Module::where('name', 'core')->exists())->toBeTrue();
});

it('refuses to run twice without force', function () {
    app(InstallSite::class)->handle(installData());

    expect(fn () => app(InstallSite::class)->handle(installData()))->toThrow(SiteAlreadyInstalledException::class)
        ->and(\Ladcms\Core\Models\User::count())->toBe(1);
});

it('with force re-seeds without duplicating', function () {
    app(InstallSite::class)->handle(installData());
    app(InstallSite::class)->handle(new SiteInstallData('X', 'ru', 'second', 'S', 's@example.com', 'another-secret-12'), force: true);

    expect(Role::count())->toBe(8)->and(\Ladcms\Core\Models\User::count())->toBe(2);
});
```

`InstallSiteCommandTest.php`:
```php
it('installs the site non-interactively', function () {
    $this->artisan('ladcms:install', [
        '--site-name' => 'Site', '--language' => 'ru', '--admin-login' => 'root', '--admin-name' => 'Root',
        '--admin-email' => 'root@example.com', '--admin-password' => 'root-password-123',
    ])->expectsOutputToContain('LADCMS installed')->assertSuccessful();

    expect(\Ladcms\Core\Models\User::where('login', 'root')->exists())->toBeTrue();
});

it('reports an already installed site', function () {
    $this->artisan('ladcms:install', ['--site-name' => 'S', '--admin-login' => 'root', '--admin-name' => 'R', '--admin-email' => 'r@example.com', '--admin-password' => 'root-password-123'])->assertSuccessful();
    $this->artisan('ladcms:install', ['--site-name' => 'S', '--admin-login' => 'root2', '--admin-name' => 'R', '--admin-email' => 'r2@example.com', '--admin-password' => 'root-password-123'])
        ->expectsOutputToContain('already installed')->assertFailed();
});

it('still installs a module when a name is given', function () {
    $this->app->register(\Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider::class);
    $path = tempnam(sys_get_temp_dir(), 'pkg'); file_put_contents($path, '{"dependencies":{}}'); config()->set('core.package_json', $path);

    $this->artisan('ladcms:install', ['module' => 'dummy'])->expectsOutputToContain('dummy 1.0.0 installed')->assertSuccessful();
    @unlink($path);
});
```
(файл с `uses(RefreshDatabase::class)` и `declare`).

- [ ] **Step 2: FAIL.**

- [ ] **Step 3: Реализация**

`SiteInstallData`:
```php
final readonly class SiteInstallData
{
    public function __construct(
        public string $siteName,
        public string $language,
        public string $adminLogin,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
    ) {}
}
```
`SiteAlreadyInstalledException extends RuntimeException` (сообщение `LADCMS is already installed. Use --force to re-run seeders.`).

`CreateAdministrator`:
```php
public function handle(string $login, string $name, string $email, string $password): User
{
    $login = LoginRules::normalize($login);
    Validator::make(
        ['login' => $login, 'name' => $name, 'email' => $email, 'password' => $password],
        ['login' => LoginRules::rules(), 'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')], 'password' => ['required', 'string', 'min:12']],
    )->validate();

    $user = User::create(['login' => $login, 'name' => $name, 'email' => $email, 'password' => $password]);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole('creator');

    return $user;
}
```
`InstallSite`:
```php
public function handle(SiteInstallData $data, bool $force = false): User
{
    Artisan::call('migrate', ['--force' => true]);

    if (! $force && Module::where('name', 'core')->exists()) {
        throw new SiteAlreadyInstalledException;
    }

    (new RolesSeeder)->run();
    (new PermissionsSeeder)->run();

    $general = app(GeneralSettings::class);
    $general->site_name = $data->siteName;
    $general->default_language = $data->language;
    $general->save();

    $admin = $this->createAdministrator->handle($data->adminLogin, $data->adminName, $data->adminEmail, $data->adminPassword);

    Module::firstOrCreate(['name' => 'core'], ['version' => CoreServiceProvider::VERSION, 'installed_at' => now(), 'is_enabled' => true]);

    return $admin;
}
```
Команда: в `InstallModuleCommand` сигнатура `ladcms:install {module? : Module name} {--site-name=} {--language=ru} {--admin-login=} {--admin-name=} {--admin-email=} {--admin-password=} {--force}`; если `module` задан — прежняя логика; иначе — собрать `SiteInstallData` из опций, недостающие запросить через `Laravel\Prompts\text()`/`password()` (только если `$this->input->isInteractive()`; в неинтерактивном режиме отсутствие опции → `error` + FAILURE), вызвать `InstallSite`, вывести `LADCMS installed. Log in as <login>.`; `SiteAlreadyInstalledException` → `error($e->getMessage())`, FAILURE; `ValidationException` → вывести ошибки, FAILURE. Описание команды обновить: `Install LADCMS (no argument) or a module (with name)`.

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): ladcms:install site wizard`.

---

### Task 7: Баны: модель, `BanUser`/`LiftBan`, `CheckBan`, `/banned`

**Files:**
- Create: `packages/core/database/migrations/2026_10_15_000006_create_bans_table.php`, `packages/core/src/Models/Ban.php`, `packages/core/src/Enums/BanType.php`, `packages/core/src/Actions/Moderation/{BanUser,LiftBan}.php`, `packages/core/src/Http/Middleware/CheckBan.php`, `packages/core/src/Http/Controllers/BannedController.php`, `packages/core/resources/js/pages/banned.tsx`, `packages/core/tests/Unit/Actions/BanUserTest.php`, `packages/core/tests/Feature/CheckBanTest.php`
- Modify: `packages/core/src/Models/User.php`, `packages/core/routes/web.php`, `packages/core/src/CoreServiceProvider.php`

**Interfaces:**
- Produces: `bans` (`user_id`, `banned_by_id`, `type` string(8) `full|readonly`, `reason_code` string(32), `comment` text null, `starts_at`, `expires_at` null, timestamps); `Ban` модель, `scopeActive()` (`starts_at <= now AND (expires_at IS NULL OR expires_at > now)`), `isActive()`.
- Produces: `BanUser::handle(User $actor, User $target, BanType $type, string $reasonCode, ?\DateTimeInterface $expiresAt, ?string $comment = null): Ban` — требует `$actor->can('users.ban')` и `$actor->outranks($target)` (иначе `AuthorizationException`), `reason_code` должен быть в `ModerationSettings::$violation_codes`, срок — в пределах `min_days..max_days` кода (null = перманентный допустим только если `max_days === null`); пишет activity log (Task 10).
- Produces: `LiftBan::handle(User $actor, Ban $ban): void` (те же права; ставит `expires_at = now()`).
- Produces: `User::activeBan(): ?Ban`, `User::isBanned(): bool`, `User::canWrite(): bool` (нет активного бана любого типа и не read-only по `readonly_hours`).
- Produces: `CheckBan` middleware (алиас `ladcms.ban`, добавляется в группу `web` core-провайдером): `full` → `/banned` (redirect) или 403 JSON; роут `GET /banned` (`banned`) рендерит `core::banned` с `reason_code`, `comment`, `expires_at`; незабаненного с `/banned` редиректит на `home`.

- [ ] **Step 1: Тесты (падающие)**

`BanUserTest.php`:
```php
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
```

`CheckBanTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('web')->get('/probe', fn () => 'ok')->name('probe');
    Route::middleware('web')->post('/probe', fn () => 'posted');
});

function banned(BanType $type, ?\DateTimeInterface $expires = null): User
{
    $user = User::factory()->create();
    Ban::create(['user_id' => $user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => $type, 'reason_code' => 'spam', 'starts_at' => now()->subMinute(), 'expires_at' => $expires]);

    return $user;
}

it('redirects fully banned users to /banned and blocks posts', function () {
    $user = banned(BanType::Full, now()->addDay());

    $this->actingAs($user)->get('/probe')->assertRedirect('/banned');
    $this->actingAs($user)->post('/probe')->assertRedirect('/banned');
    $this->actingAs($user)->postJson('/probe')->assertForbidden();
});

it('lets readonly-banned users browse', function () {
    $this->actingAs(banned(BanType::Readonly))->get('/probe')->assertOk();
});

it('ignores expired bans and guests', function () {
    $this->actingAs(banned(BanType::Full, now()->subHour()))->get('/probe')->assertOk();
    $this->get('/probe')->assertOk();
});

it('shows the banned page only to banned users', function () {
    $user = banned(BanType::Full, now()->addDay());

    $this->actingAs($user)->get('/banned')->assertOk()->assertInertia(fn ($page) => $page->component('core::banned')->where('reason_code', 'spam'));
    $this->actingAs(User::factory()->create())->get('/banned')->assertRedirect('/');
});
```

- [ ] **Step 2: FAIL.**

- [ ] **Step 3: Реализация**

`BanType` enum (`Full = 'full'`, `Readonly = 'readonly'`). Миграция `bans` по Interfaces (FK `user_id`/`banned_by_id` → users, `cascadeOnDelete` для user_id, `nullOnDelete` для banned_by_id; индекс `(user_id, expires_at)`). Модель `Ban`: fillable все, casts `type` → `BanType`, `starts_at`/`expires_at` datetime; `scopeActive(Builder $q)`; `isActive(): bool`; `user()`, `bannedBy()`.

`User`:
```php
public function bans(): HasMany { return $this->hasMany(Ban::class); }
public function activeBan(): ?Ban { return $this->bans()->active()->latest('starts_at')->first(); }
public function isBanned(): bool { return $this->activeBan() !== null; }
public function canWrite(): bool
{
    if ($this->isBanned()) { return false; }
    $hours = app(RegistrationSettings::class)->readonly_hours;

    return $hours === 0 || $this->created_at === null || $this->created_at->addHours($hours)->isPast();
}
```

`BanUser`:
```php
public function handle(User $actor, User $target, BanType $type, string $reasonCode, ?\DateTimeInterface $expiresAt, ?string $comment = null): Ban
{
    if (! $actor->can('users.ban') || ! $actor->outranks($target)) {
        throw new AuthorizationException('You cannot ban this user.');
    }

    $codes = collect(app(ModerationSettings::class)->violation_codes)->keyBy('code');
    Validator::make(['reason_code' => $reasonCode], ['reason_code' => ['required', Rule::in($codes->keys())]])->validate();
    $code = $codes[$reasonCode];
    $days = $expiresAt === null ? null : (int) ceil(now()->diffInSeconds($expiresAt, absolute: true) / 86400);

    if ($expiresAt === null && $code['max_days'] !== null) {
        throw ValidationException::withMessages(['expires_at' => 'This violation code does not allow a permanent ban.']);
    }
    if ($days !== null && ($days < $code['min_days'] || ($code['max_days'] !== null && $days > $code['max_days']))) {
        throw ValidationException::withMessages(['expires_at' => "Duration must be between {$code['min_days']} and ".($code['max_days'] ?? '∞').' days.']);
    }

    return Ban::create(['user_id' => $target->id, 'banned_by_id' => $actor->id, 'type' => $type, 'reason_code' => $reasonCode, 'comment' => $comment, 'starts_at' => now(), 'expires_at' => $expiresAt]);
}
```
`LiftBan::handle(User $actor, Ban $ban)`: та же проверка прав против `$ban->user`; `$ban->update(['expires_at' => now()])`.

`CheckBan`:
```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if ($user instanceof User && ! $request->routeIs('banned', 'logout') && ($ban = $user->activeBan()) && $ban->type === BanType::Full) {
        if ($request->expectsJson()) {
            abort(403, 'Your account is banned.');
        }

        return redirect()->route('banned');
    }

    return $next($request);
}
```
В `CoreServiceProvider::packageBooted()`: `$router->aliasMiddleware('ladcms.ban', CheckBan::class); $router->pushMiddlewareToGroup('web', CheckBan::class);` (Testbench и app используют группу `web`).

Роут и контроллер: `Route::get('/banned', BannedController::class)->middleware('auth')->name('banned');` → если `! $user->isBanned()` → `redirect()->route('home')`; иначе `Inertia::render('core::banned', ['reason_code' => …, 'comment' => …, 'expires_at' => $ban->expires_at?->toIso8601String()])`.

`packages/core/resources/js/pages/banned.tsx` — простая страница: заголовок «Доступ ограничен», код, комментарий, дата (или «бессрочно»), кнопка выхода (`<Link href="/logout" method="post" as="button">`).

Тест `/banned` редирект `'/'` — в Testbench core `routes/web.php` объявляет `home` на `/` (есть с плана 1).

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): bans with ranking checks, CheckBan middleware and banned page`.

---

### Task 8: Жалобы: модель, `FileComplaint`, `ReviewComplaint`

**Files:**
- Create: `packages/core/database/migrations/2026_10_15_000007_create_complaints_table.php`, `packages/core/src/Models/Complaint.php`, `packages/core/src/Enums/ComplaintStatus.php`, `packages/core/src/Actions/Moderation/{FileComplaint,ReviewComplaint}.php`, `packages/core/src/Notifications/ComplaintFiled.php`, `packages/core/tests/Unit/Actions/ComplaintsTest.php`

**Interfaces:**
- Produces: `complaints` (`user_id`, `target_user_id` null, `reportable_type` + `reportable_id` (morph, nullable), `reason_code`, `comment` text, `status` string(16) default `pending`, `reviewed_by_id` null, `reviewed_at` null, timestamps); enum `ComplaintStatus { Pending='pending', Reviewed='reviewed', Resolved='resolved', Rejected='rejected' }`.
- Produces: `FileComplaint::handle(User $author, string $reasonCode, string $comment, ?Model $reportable = null, ?User $targetUser = null): Complaint` — валидирует код; автор должен `canWrite()`; антидубль: та же пара `(user_id, reportable)` со статусом `pending` → `ValidationException`; уведомляет всех пользователей с `complaints.review` (`ComplaintFiled`, канал `database`).
- Produces: `ReviewComplaint::handle(User $reviewer, Complaint $complaint, ComplaintStatus $status): Complaint` — требует `complaints.review`, статус ≠ pending, ставит `reviewed_by_id/at`.

- [ ] **Step 1: Тест (падающий)** — `ComplaintsTest.php`: (а) подаёт жалобу на пользователя с `reason_code = 'spam'`, проверяет запись и что модератор получил `ComplaintFiled` в `notifications` (`Notification::fake()` + `assertSentTo`); (б) дубль pending → `ValidationException`; (в) неизвестный код → `ValidationException`; (г) забаненный автор → `AuthorizationException`; (д) `ReviewComplaint` обычным пользователем → `AuthorizationException`, модератором → статус `resolved`, `reviewed_by_id` = модератор; (е) `ReviewComplaint` со статусом `Pending` → `InvalidArgumentException`. Код тестов — по образцу Task 7 (сидеры в `beforeEach`, `Notification::fake()`).

- [ ] **Step 2: FAIL.** — [ ] **Step 3: Реализация** по Interfaces: миграция (`nullableMorphs('reportable')`, индексы `status`, `(user_id, status)`); `Complaint` (casts `status` → enum, `reviewed_at` datetime; `reportable()` morphTo, `author()`, `targetUser()`, `reviewer()`; `scopePending`); `ComplaintFiled extends Notification` (`via` → `['database']`, `toDatabase` → `['complaint_id', 'reason_code', 'author_login']`); `FileComplaint` (валидация кода через `ModerationSettings`, проверка `canWrite()`, дубль по `where('user_id')->where('reportable_type')->where('reportable_id')->where('status','pending')`, создание, `Notification::send(User::permission('complaints.review')->get(), new ComplaintFiled($complaint))`); `ReviewComplaint`. Таблица `notifications` нужна для `database`-канала — создаётся в Task 11; в этом тесте используется `Notification::fake()`, БД не нужна.

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): complaints with moderator notifications`.

---

### Task 9: Политики `UserPolicy`, `RolePolicy` и действия админа над пользователем

**Files:**
- Create: `packages/core/src/Policies/{UserPolicy,RolePolicy}.php`, `packages/core/src/Actions/Users/{AssignRoles,MarkSuspicious,DeleteUser}.php`, `packages/core/tests/Unit/Actions/UserAdminActionsTest.php`, `packages/core/tests/Feature/PoliciesTest.php`
- Modify: `packages/core/src/CoreServiceProvider.php` (`Gate::policy`)

**Interfaces:**
- `UserPolicy`: `viewAny(users.view)`, `view`, `update(User $actor, User $target)` = `users.edit` && (`actor->is(target)` || `outranks`), `ban` = `users.ban` && `outranks`, `assignRoles(User $actor, User $target)` = `users.assign-roles` && `outranks`, `delete` = `users.delete` && `outranks` && `!target->isCreator()`.
- `RolePolicy`: `viewAny`/`update`/`create` = `roles.manage`; `delete(User, Role $role)` = `roles.manage && !role->is_system`.
- `AssignRoles::handle(User $actor, User $target, array $roleNames): User` — через policy `assignRoles`; нельзя выдать роль с `level >= actor->level()` (кроме creator); `syncRoles`.
- `MarkSuspicious::handle(User $actor, User $target, bool $flag, ?string $note): User` — `users.edit` && outranks.
- `DeleteUser::handle(User $actor, User $target): void` — policy `delete`; soft delete; запрещено удалять последнего `creator`.

- [ ] **Step 1: Тесты (падающие)** — `PoliciesTest`: таблица случаев через `Gate::forUser($actor)->allows('update', $target)` и т.д. для пар (moderator→user ✓ ban, moderator→moderator ✗ ban, admin→moderator ✓ assignRoles, admin→admin ✗, creator→anyone ✓, user→self ✓ update, user→other ✗ update, admin→creator ✗ delete, `RolePolicy` delete `vip` ✗, non-system role ✓). `UserAdminActionsTest`: `AssignRoles` — admin выдаёт `moderator` ✓, `admin` ✗ (level ≥), `main_admin` ✗, creator выдаёт что угодно ✓; `MarkSuspicious` записывает флаг и note; `DeleteUser` soft-удаляет, последнего creator удалить нельзя (`InvalidArgumentException`), creator может удалить другого creator, если их двое.

- [ ] **Step 2: FAIL.** — [ ] **Step 3: Реализация** по Interfaces; `Gate::policy(User::class, UserPolicy::class); Gate::policy(Role::class, RolePolicy::class);` в `packageBooted`. Actions используют `Gate::forUser($actor)->authorize('ability', $target)` (бросает `AuthorizationException`).

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): user and role policies with admin actions`.

---

### Task 10: Онлайн-трекинг и аудит действий

**Files:**
- Create: `packages/core/src/Http/Middleware/TrackOnline.php`, `packages/core/database/migrations/2026_10_15_000008_create_activity_log_table.php` (+ две миграции spatie `add_event_column`, `add_batch_uuid_column` — скопировать содержимое стабов из `vendor/spatie/laravel-activitylog/database/migrations/*.stub` в один файл или три с последовательными номерами), `packages/core/src/Support/Audit.php`, `packages/core/tests/Feature/TrackOnlineTest.php`, `packages/core/tests/Unit/AuditTest.php`
- Modify: `packages/core/composer.json` (`spatie/laravel-activitylog ^5.1`), `tests/TestCase.php` (провайдер `Spatie\Activitylog\ActivitylogServiceProvider`), `packages/core/src/Models/User.php` (`scopeOnline`, `isOnline()`), `packages/core/src/CoreServiceProvider.php`, Actions из Task 7/9 (запись аудита)

**Interfaces:**
- `TrackOnline` (в группу `web`): для авторизованного обновляет `last_activity_at`, если прошло ≥ 60 с (без событий модели: `User::whereKey(...)->update([...])`), не трогает сессию; `User::scopeOnline()` — `last_activity_at > now()-10min`; `isOnline()`.
- `Audit::log(string $event, User $actor, ?Model $subject = null, array $properties = []): void` — обёртка над `activity()->causedBy($actor)->performedOn($subject)->withProperties($properties)->event($event)->log($event)`; log name `admin`.
- `BanUser`, `LiftBan`, `AssignRoles`, `MarkSuspicious`, `DeleteUser`, `ReviewComplaint` пишут аудит (`ban.created`, `ban.lifted`, `roles.assigned`, `user.suspicious`, `user.deleted`, `complaint.reviewed`).

- [ ] **Step 1: Тесты (падающие)** — `TrackOnlineTest`: запрос авторизованным ставит `last_activity_at`; второй запрос через 30 с не меняет значение; через 61 с — меняет; гость — ничего; `User::online()->count()` считает только свежих. `AuditTest`: после `BanUser` в `activity_log` есть запись с `event = 'ban.created'`, `causer_id` = модератор, `subject` = цель; `Audit::log` без subject сохраняет properties.

- [ ] **Step 2: FAIL.** — [ ] **Step 3: Реализация**: `composer require` в core (правка composer.json + `composer update ladcms/core` в корне и `sail composer update ladcms/core` в app), миграции activitylog (таблица `activity_log` с `event`, `batch_uuid`), конфиг activitylog — дефолтный из пакета; `TrackOnline` с проверкой `$user->last_activity_at?->diffInSeconds(now()) >= 60`; регистрация в `web` через `pushMiddlewareToGroup`; `Audit`; вставить вызовы `Audit::log(...)` в Actions.

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): online tracking and admin audit log`.

---

### Task 11: Уведомления, mentions, `mention-search`

**Files:**
- Create: `packages/core/database/migrations/2026_10_15_000009_create_notifications_table.php`, `packages/core/src/Services/MentionService.php`, `packages/core/src/Notifications/{UserMentioned,UserBanned}.php`, `packages/core/src/Http/Controllers/Api/MentionSearchController.php`, `packages/core/tests/Unit/MentionServiceTest.php`, `packages/core/tests/Feature/MentionSearchTest.php`, `packages/core/tests/Feature/NotificationsTest.php`
- Modify: `packages/core/routes/web.php`, `packages/core/src/Actions/Moderation/BanUser.php` (отправка `UserBanned`)

**Interfaces:**
- `notifications` — стандартная схема Laravel (uuid id, type, morphs notifiable, text data, read_at, timestamps).
- `MentionService::extract(string $html): Collection<int, User>` — ищет `<span data-mention data-user-id="N">`, возвращает существующих пользователей без дублей; `MentionService::notify(string $html, User $author, Model $context, string $url): int` — отправляет `UserMentioned` всем упомянутым, кроме автора, возвращает число.
- `UserMentioned` (`database`): `toDatabase` → `['author_id','author_login','context_type','context_id','url']`. `UserBanned` (`database` + `mail`): `['ban_id','reason_code','expires_at']`.
- `GET /api/users/mention-search?q=` (web-группа, `auth`, `throttle:60,1`): `q` min 1, max 32; ищет `login LIKE q%` или `name LIKE q%`, не удалённых, limit 10; ответ `[{id, login, name, avatar_url}]`; пустой `q` → `[]`.

- [ ] **Step 1: Тесты (падающие)** — `MentionServiceTest` (extract: 2 упоминания + несуществующий id + дубль → 2 пользователя; notify: `Notification::fake()`, автор исключён); `MentionSearchTest` (гость → 401/redirect; `q=va` → содержит `vadim`, не содержит `anna`; limit 10; `q` пустой → `[]`); `NotificationsTest` (реальная БД: `BanUser` создаёт запись в `notifications` для цели с `type = UserBanned::class`, `Notification::fake()` не используется; `Mail::fake()` для канала mail).

- [ ] **Step 2: FAIL.** — [ ] **Step 3: Реализация** по Interfaces (`avatar_url` аксессор появится в Task 12 — здесь вернуть `null`-safe через `$user->avatar_url ?? null`; добавить аксессор-заглушку `getAvatarUrlAttribute(): ?string { return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null; }` уже сейчас). Роут в `routes/web.php`: `Route::middleware(['auth', 'throttle:60,1'])->get('/api/users/mention-search', MentionSearchController::class)->name('api.users.mention-search');`.

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): notifications table, mention service and mention search`.

---

### Task 12: Аватары

**Files:**
- Create: `packages/core/src/Actions/Users/{UpdateAvatar,RemoveAvatar}.php`, `packages/core/tests/Unit/Actions/UpdateAvatarTest.php`
- Modify: `packages/core/composer.json` (`intervention/image ^3.11`), `packages/core/src/Models/User.php`, `packages/core/config/core.php` (`'avatars' => ['disk' => 'public', 'path' => 'avatars', 'sizes' => ['md' => 256, 'sm' => 64], 'max_kb' => 2048]`)

**Interfaces:**
- `UpdateAvatar::handle(User $user, UploadedFile $file): User` — валидация (`image`, `mimes:jpg,jpeg,png,webp`, `max:<max_kb>`), ресайз `cover()` в два размера (`avatars/{id}/md.jpg`, `avatars/{id}/sm.jpg`), запись `avatar_path = avatars/{id}/md.jpg`, удаление старых файлов; `RemoveAvatar::handle(User): User`.
- `User::avatarUrl(string $size = 'md'): ?string`; аксессор `avatar_url` = `avatarUrl('md')`.

- [ ] **Step 1: Тест (падающий)** — `Storage::fake('public')`; `UploadedFile::fake()->image('a.png', 800, 600)` (требует GD); после `handle` существуют `avatars/{id}/md.jpg` и `sm.jpg`, размеры 256×256 и 64×64 (прочитать через `getimagesize`), `avatar_url` не null; повторная загрузка заменяет файлы; `RemoveAvatar` удаляет файлы и обнуляет поле; не-изображение → `ValidationException`.

- [ ] **Step 2: FAIL.** — [ ] **Step 3: Реализация**: `composer require intervention/image` (core composer.json + `composer update ladcms/core` в корне и app); `ImageManager::gd()` (проверить сигнатуру в vendor; если статики нет — `new ImageManager(new \Intervention\Image\Drivers\Gd\Driver)`), `read($file->getRealPath())->cover($size, $size)->toJpeg(85)->toString()` → `Storage::disk($disk)->put($path, $binary)`. Убедиться, что в Sail-образе есть `ext-gd` (есть: `php8.4-gd`).

- [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): avatar upload with resizing`.

---

### Task 13: Интеграция auth в приложении (Fortify: login/email, регистрация с `login`, настройки регистрации)

**Files:**
- Create: `packages/core/src/Actions/Auth/{AuthenticateByLoginOrEmail,RegisterUser}.php`, `packages/core/src/Http/Middleware/EnsureRegistrationOpen.php`, `packages/core/src/Listeners/AssignDefaultRole.php`, `packages/core/tests/Unit/Actions/AuthActionsTest.php`
- Modify: `app/app/Models/User.php`, `app/app/Providers/FortifyServiceProvider.php`, `app/app/Actions/Fortify/CreateNewUser.php`, `app/app/Concerns/ProfileValidationRules.php`, `app/database/factories/UserFactory.php`, `app/resources/js/pages/auth/login.tsx`, `app/resources/js/pages/auth/register.tsx`, `app/resources/js/types/auth.ts`, `app/app/Http/Middleware/HandleInertiaRequests.php`, `app/tests/Feature/Auth/{AuthenticationTest,RegistrationTest}.php`, `app/bootstrap/app.php` (при необходимости), `packages/core/src/CoreServiceProvider.php` (event listener)

**Interfaces:**
- `AuthenticateByLoginOrEmail::handle(string $identifier, string $password): ?User` — по `@` в идентификаторе ищет email (lower) или login (normalize); проверяет `Hash::check`; удалённых не пускает.
- `RegisterUser::handle(array $input, ?string $ip = null): User` — правила: `login` (`LoginRules`), `name` (`required|string|max:255`), `email` (`required|email|unique`), `password` (`required|confirmed|min:12` — через `Password::defaults()` при наличии), `rules_accepted` (`accepted` если `RegistrationSettings::$require_rules`); бросает `AuthorizationException`, если `!open`; создаёт пользователя (`registered_ip`, `language` = `GeneralSettings::$default_language`), событие `Registered` диспатчит Fortify, не Action.
- `AssignDefaultRole` слушает `Illuminate\Auth\Events\Registered` → `assignRole(Role::defaultForRegistration())`.
- `EnsureRegistrationOpen` middleware: для `GET /register` → 404, для `POST /register` → 403, если `!RegistrationSettings::$open`.
- App: `App\Models\User extends Ladcms\Core\Models\User` + kit-трейты (`TwoFactorAuthenticatable`, `PasskeyAuthenticatable`, `implements MustVerifyEmail, PasskeyUser`), **без** атрибутов `#[Fillable]`/`#[Hidden]` (родитель определяет), фабрика приложения расширяет core-фабрику; `FortifyServiceProvider`: `Fortify::authenticateUsing(fn (Request $r) => app(AuthenticateByLoginOrEmail::class)->handle((string) $r->input(Fortify::username()), (string) $r->input('password')))`; `CreateNewUser::create()` → `app(RegisterUser::class)->handle($input, request()->ip())`; роуты `/register` получают `EnsureRegistrationOpen` (через `Fortify::registerView` + middleware в `config/fortify.php` нельзя точечно — добавить middleware в `bootstrap/app.php` через `$middleware->web(append: [])`? Нет: использовать `Route::middleware` невозможно для Fortify-роутов; решение — в `EnsureRegistrationOpen` проверять `$request->routeIs('register')` и подключить его в группу `web` (как `CheckBan`)).
- Frontend: `login.tsx` — поле `email` остаётся именем поля (Fortify `username`), но `type="text"`, label «Логин или email», автокомплит `username`; `register.tsx` — новое поле `login` перед `name` (+ ошибки), чекбокс правил не добавляем (plan 3 — публичная страница правил; пока `require_rules=false` по умолчанию). `types/auth.ts` — `User` получает `login`, `avatar_url`, `roles: string[]`, `permissions: string[]`; `HandleInertiaRequests::share` → `'auth' => ['user' => $user ? UserResource... ]` — сделать в core `Ladcms\Core\Http\Resources\CurrentUserResource` (`id, login, name, email, avatar_url, roles (names), permissions (getAllPermissions names), is_banned, can_write`) и использовать его в приложении.
- Тесты приложения: `AuthenticationTest` — логин по `login` и по email, неверный пароль; `RegistrationTest` — успешная регистрация с `login`, ошибка без `login`, закрытая регистрация (`RegistrationSettings::fake(['open' => false, ...])` или save) → GET 404, POST 403; пользователь после регистрации имеет роль `user`.

- [ ] **Step 1: Тесты (падающие)** — core `AuthActionsTest` (unit для двух Actions: 6 кейсов — login, email, wrong password, soft-deleted → null; регистрация ok/closed/дубль login), app `Auth/*Test.php` правки (запускать в Sail).

- [ ] **Step 2: FAIL (в обоих местах).** — [ ] **Step 3: Реализация** по Interfaces. Порядок в app: обновить `User`, фабрику (`login` в `definition()`), провайдер, `CreateNewUser`, страницы, типы, `HandleInertiaRequests`; `sail artisan migrate` (миграции core уже подключены); `sail artisan wayfinder:generate --with-form`; `npm run check/types:check/build`. Для `RegistrationSettings` в app-тестах — миграции core применяются через `RefreshDatabase` (сидеры настроек — тоже миграции) — проверить, что `app/phpunit.xml`/тесты не используют `DatabaseTransactions`-only режим.

- [ ] **Step 4: PASS (root + Sail), Pint/PHPStan/Rector, `npm run check`, `types:check`, `build`. Commit** — `feat(auth): login by login or email, registration with login and settings gating`.

---

### Task 14: Локаль: `SetLocale`, список языков, `LocaleManager`

**Files:**
- Create: `packages/core/src/Services/LocaleManager.php`, `packages/core/src/Http/Middleware/SetLocale.php`, `packages/core/lang/ru/{messages,validation}.php`? — только `messages.php` с ключами, используемыми в core (`banned.title` и т.п.), `packages/core/tests/Feature/SetLocaleTest.php`
- Modify: `packages/core/config/core.php` (`'locales' => ['ru' => 'Русский', 'en' => 'English']`), `packages/core/src/CoreServiceProvider.php`

**Interfaces:**
- `LocaleManager::available(): array<string,string>`, `resolve(Request): string` — порядок: `user->language` → cookie `locale` → `Accept-Language` (первый поддерживаемый) → `GeneralSettings::$default_language` → `config('app.locale')`; `LocaleManager::current(): string`.
- `SetLocale` (группа `web`): `app()->setLocale(...)`; `Inertia::share('locale', …)` и `Inertia::share('locales', …)` в core.

- [ ] **Step 1: Тест (падающий)** — 4 кейса приоритета (пользователь `en`, cookie `en` для гостя, `Accept-Language: en-US,en;q=0.9` → `en`, неподдерживаемый `de` → дефолт `ru`); проверка `app()->getLocale()` внутри тестового роута.
- [ ] **Step 2: FAIL.** — [ ] **Step 3: Реализация.** — [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): locale resolution middleware`.

---

### Task 15: Планировщик и `ladcms:prune-unverified`

**Files:**
- Create: `packages/core/src/Console/PruneUnverifiedUsersCommand.php`, `packages/core/src/Actions/Users/PruneUnverifiedUsers.php`, `packages/core/tests/Unit/Actions/PruneUnverifiedUsersTest.php`, `packages/core/tests/Feature/ScheduleTest.php`
- Modify: `packages/core/src/CoreServiceProvider.php`, `packages/core/config/core.php` (`'prune_unverified_after_days' => 7`)

**Interfaces:**
- `PruneUnverifiedUsers::handle(int $days): int` — `forceDelete` пользователей без `email_verified_at`, созданных раньше `now()-days`, без ролей выше `user`; возвращает число.
- Команда `ladcms:prune-unverified {--days=}`; расписание `daily()` через `callAfterResolving(Schedule::class, …)`; `activitylog:clean` — `daily()`.

- [ ] **Step 1: Тесты (падающие)** — Action: удаляет только старых неподтверждённых; `ScheduleTest`: `app(Schedule::class)->events()` содержит команды `ladcms:prune-unverified` и `activitylog:clean` с выражением `0 0 * * *`.
- [ ] **Step 2: FAIL.** — [ ] **Step 3: Реализация.** — [ ] **Step 4: PASS, Pint/PHPStan/Rector, Commit** — `feat(core): scheduled pruning of unverified users`.

---

### Task 16: Обновить документацию и гайдлайны

**Files:**
- Modify: `packages/core/resources/boost/guidelines/core.blade.php` (разделы: User/роли/уровни, `HasSettings`, permissions, Actions-список), `packages/core/README.md` (что входит в core после плана 2a, команды), `.ai/rules/actions.md` (добавить правило: действия над пользователями проверяют права через policy + `outranks()`), `docs/superpowers/specs/2026-10-14-ladcms-foundation-and-core-design.md` (§4.6: шаг 3 «дефолты настроек» реализуется settings-миграциями; §5.3: `mention-search` в `web`-группе; §5.1: `TrackOnline` раз в 60 с)

- [ ] **Step 1: Внести правки.** — [ ] **Step 2: `vendor/bin/sail artisan boost:update --no-discover` без ошибок.** — [ ] **Step 3: Commit** — `docs(core): document domain layer and conventions`.

---

## Self-review (выполнено)

- **Spec coverage §5.1:** User — T1/T5/T12; Auth — T13; Роли/права — T2/T3/T9; Настройки — T4; i18n — T14; Модерация — T7/T8; Уведомления — T11 (колокольчик/страница — план 3); Mentions — T11; Онлайн — T10; Аватары — T12; Аудит — T10; Профиль (`/u/{login}`, приватность) — поля в T1, страницы — план 3; Планировщик — T15. **§5.2** таблицы: users T1, roles T2, settings T4, modules (есть), bans T7, complaints T8, login_history T5, notifications T11, activity_log T10. **§5.4** permissions — T3. **§5.3** роуты: `/banned` T7, `mention-search` T11; остальные — планы 2b/3. §4.6 шаг 3 — закрыт T4 (settings-миграции идут через `migrate`).
- **Placeholders:** T8–T10, T14–T15 описывают тесты списком кейсов, а не полным кодом — это сознательное сокращение для однотипных тестов; все сигнатуры, правила и поля заданы в Interfaces; исполнитель пишет код тестов по кейсам. Нет «TBD».
- **Type consistency:** `LoginRules::normalize/rules` (T5) используются в T6, T13; `RolesSeeder`/`PermissionsSeeder` (T2/T3) — в T6, T7, T9; `ModerationSettings::$violation_codes` структура (T4) — в T7, T8; `User::outranks()` (T2) — в T7, T9; `Audit::log` (T10) — вставляется в Actions T7/T9; `avatar_url` аксессор определён в T11 и доработан в T12 (`avatarUrl()`); `CurrentUserResource` (T13) использует `avatar_url`, `isBanned()`, `canWrite()` (T7).
- **Review Focus:** 1 → `BanUserTest` (T7); 2 → `ChangeLoginTest` (T5) + `RegistrationTest` (T13); 3 → `CheckBanTest` (T7); 4 → `InstallSiteTest`/`InstallSiteCommandTest` (T6); 5 → `RegistrationTest` (T13).
