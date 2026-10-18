<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Ui\Navigation;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->app->register(DummyServiceProvider::class);
});

function navRequest(string $uri): Request
{
    $request = Request::create($uri);
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('lists core and module items sorted by order', function () {
    $creator = User::factory()->create()->assignRole('creator');

    $items = app(Navigation::class)->items($creator, navRequest('/'));

    expect(array_column($items, 'key'))->toBe(['home', 'users', 'dummy', 'rules', 'secret'])
        ->and(array_column($items, 'order'))->toBe([10, 20, 25, 30, 40]);
});

it('resolves urls with literal fallbacks for routes that do not exist yet', function () {
    $items = app(Navigation::class)->items(null, navRequest('/'));
    $urls = array_column($items, 'url', 'key');

    expect($urls['home'])->toBe(url('/'))
        ->and($urls['users'])->toEndWith('/users')
        ->and($urls['rules'])->toEndWith('/rules')
        ->and($urls['dummy'])->toBe(url('/dummy'));
});

it('hides permission-gated items from guests and users without the permission', function () {
    $nav = app(Navigation::class);

    expect(array_column($nav->items(null, navRequest('/')), 'key'))->not->toContain('secret')
        ->and(array_column($nav->items(User::factory()->create(), navRequest('/')), 'key'))->not->toContain('secret');
});

it('translates labels with a fallback to the declared label', function () {
    $items = app(Navigation::class)->items(null, navRequest('/'));
    $labels = array_column($items, 'label', 'key');

    expect($labels['home'])->toBe('Home')
        ->and($labels['dummy'])->toBe('Dummy');

    app()->setLocale('ru');
    expect(array_column(app(Navigation::class)->items(null, navRequest('/')), 'label', 'key')['home'])->toBe('Главная');
});

it('marks the item of the current route as active', function () {
    $items = app(Navigation::class)->items(null, navRequest('/dummy'));
    $active = array_column($items, 'active', 'key');

    expect($active['dummy'])->toBeTrue()
        ->and($active['home'])->toBeFalse()
        ->and($active['users'])->toBeFalse();
});

it('marks the fallback-path item active by url prefix', function () {
    Route::get('/users/{any}', fn () => 'x')->middleware('web');

    $items = app(Navigation::class)->items(null, navRequest('/users/abc'));

    expect(array_column($items, 'active', 'key')['users'])->toBeTrue();
});
