<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\AppearanceSettings;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\ModerationSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');
});

it('renders every admin GET route as an existing core page for the creator', function () {
    $creator = User::factory()->create()->assignRole('creator');
    $member = User::factory()->create();
    $role = Role::findByName('moderator', 'web');
    $complaint = Complaint::query()->create([
        'user_id' => $member->id,
        'target_user_id' => $creator->id,
        'reason_code' => 'spam',
        'comment' => 'c',
        'status' => ComplaintStatus::Pending,
    ]);
    expect($complaint->exists)->toBeTrue();
    $groups = array_map(fn (string $class): string => $class::group(), [
        GeneralSettings::class, RegistrationSettings::class, ModerationSettings::class, AppearanceSettings::class,
    ]);

    $uris = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var LaravelRoute $route */
        $name = (string) $route->getName();
        if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $params = match ($name) {
            'admin.users.show' => ['user' => $member->id],
            'admin.roles.edit' => ['role' => $role->id],
            'admin.settings.edit' => null,
            default => [],
        };
        if ($params === null) {
            foreach ($groups as $group) {
                $uris[$name.':'.$group] = route($name, ['group' => $group], false);
            }

            continue;
        }
        $uris[$name] = route($name, $params, false);
    }

    expect($uris)->toHaveKeys(['admin.dashboard', 'admin.users.show', 'admin.roles.edit', 'admin.settings.edit:general', 'admin.activity.index']);
    expect(count($uris))->toBeGreaterThanOrEqual(13);

    foreach ($uris as $key => $uri) {
        $response = $this->actingAs($creator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get($uri)
            ->assertOk();

        $inertia = AssertableInertia::fromTestResponse($response)->toArray();
        $component = $inertia['component'];
        expect($component)->toStartWith('core::admin/', $key);

        // Неразрешённая ResourceCollection сериализуется Inertia 3 как {data: [...]} —
        // фронт ждёт массив, а получает объект (см. баг с roles на /admin/users).
        foreach ($inertia['props'] as $prop => $value) {
            $wrapped = is_array($value) && $value !== [] && array_keys($value) === ['data'];
            expect($wrapped)->toBeFalse("{$key}: prop '{$prop}' is an unresolved resource collection ({data: [...]}); call ->resolve(\$request)");
        }

        // `true` — страница обязана существовать на диске (резолв через ModulePageFinder).
        $response->assertInertia(fn (AssertableInertia $page) => $page->component($component, true));
    }
});

it('shares the i18n map on admin routes only', function () {
    $this->app->register(DummyServiceProvider::class);
    Route::get('/plain', fn () => Inertia\Inertia::render('core::home'))->middleware('web');
    $creator = User::factory()->create(['language' => 'en'])->assignRole('creator');
    $auth = fn () => $this->actingAs($creator)->withSession(['auth.password_confirmed_at' => time()]);

    $auth()->get('/admin')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('i18n', fn ($map) => $map['core::admin.saved'] === 'Saved'
            && is_string($map['core::admin.roles.names.creator'])
            && $map['dummy::admin.title'] === 'Dummy items'
            && $map['dummy::admin.nested.hello'] === 'Hello :name'));

    $creator->update(['language' => 'ru']);
    $auth()->get('/admin')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('i18n', fn ($map) => $map['core::admin.saved'] === 'Сохранено'
            && $map['dummy::admin.title'] === 'Элементы Dummy'));

    $auth()->get('/plain')->assertInertia(fn (AssertableInertia $page) => $page->where('i18n', fn ($map) => ! array_key_exists('core::admin.saved', $map->toArray())));
});
