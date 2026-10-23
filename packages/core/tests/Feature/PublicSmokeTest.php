<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
});

/**
 * @return array<string, string> route name => URI of every non-admin GET route (parameters seeded)
 */
function publicGetUris(User $target): array
{
    $uris = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var LaravelRoute $route */
        $name = (string) $route->getName();
        if (! in_array('GET', $route->methods(), true)
            || str_starts_with($name, 'admin.')
            || str_starts_with($route->uri(), 'admin')
            || $name === '' // stub/anonymous routes of the harness
            || $name === 'login') {
            continue;
        }
        $params = match ($name) {
            'users.show' => ['login' => $target->login],
            default => [],
        };
        $uris[$name] = route($name, $params, false);
    }

    return $uris;
}

/** Inertia-страница: компонент существует на диске, нет неразрешённых ResourceCollection ({data: [...]}). */
function assertInertiaPage(mixed $test, TestResponse $response, string $key): void
{
    $inertia = AssertableInertia::fromTestResponse($response)->toArray();
    $component = $inertia['component'];
    expect($component)->toStartWith('core::', $key);

    foreach ($inertia['props'] as $prop => $value) {
        $wrapped = is_array($value) && $value !== [] && array_keys($value) === ['data'];
        // Пагинаторы страниц намеренно {data, meta}; голый {data: [...]} — баг ResourceCollection.
        expect($wrapped)->toBeFalse("{$key}: prop '{$prop}' is an unresolved resource collection ({data: [...]}); call ->resolve(\$request)");
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page->component($component, true));
}

it('has the expected public GET routes', function () {
    $uris = publicGetUris(User::factory()->create());

    expect($uris)->toHaveKeys(['home', 'users.index', 'users.show', 'rules', 'notifications.page', 'banned', 'settings.profile', 'settings.privacy', 'settings.notifications', 'settings.appearance']);
});

it('serves every public GET route to a guest: 200, or a login redirect/401 for auth-only ones', function () {
    $target = User::factory()->create();
    $pages = 0;

    foreach (publicGetUris($target) as $name => $uri) {
        $response = $this->get($uri);
        $status = $response->getStatusCode();

        if ($status === 200) {
            if ($response->headers->get('X-Inertia') !== null || str_contains((string) $response->getContent(), 'data-page')) {
                assertInertiaPage($this, $response, $name);
                $pages++;
            }

            continue;
        }

        expect($status)->toBeIn([302, 401], "{$name}: unexpected guest status {$status}");
        if ($status === 302) {
            expect($response->headers->get('Location'))->toEndWith('/login', $name);
        }
    }

    expect($pages)->toBeGreaterThanOrEqual(4); // home, users.index, users.show, rules
});

it('serves every public GET route to a signed-in user', function () {
    $target = User::factory()->create();
    $viewer = User::factory()->create();

    foreach (publicGetUris($target) as $name => $uri) {
        $response = $this->actingAs($viewer)->get($uri);

        if ($name === 'banned') {
            // Без бана страница отправляет на главную.
            $response->assertRedirect(route('home'));

            continue;
        }

        if (str_starts_with($uri, '/api/')) {
            $response->assertOk();

            continue;
        }

        if ($response->isRedirect()) {
            // /settings → settings.profile
            expect($response->headers->get('Location'))->toContain('/settings', $name);

            continue;
        }

        $response->assertOk();
        assertInertiaPage($this, $response, $name);
    }
});

it('renders core::banned for a banned user and redirects everything else to it', function () {
    $user = User::factory()->create();
    Ban::query()->create([
        'user_id' => $user->id,
        'banned_by_id' => User::factory()->create()->id,
        'type' => BanType::Full,
        'starts_at' => now()->subMinute(),
        'reason_code' => 'spam',
        'comment' => 'c',
    ]);

    $this->actingAs($user)->get(route('banned'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::banned', true)
        ->where('reason_code', 'spam'));
    $this->actingAs($user)->get(route('home'))->assertRedirect(route('banned'));
});

it('redirects a web guest from /notifications to the login page', function () {
    $this->get(route('notifications.page'))->assertRedirect('/login');
});
