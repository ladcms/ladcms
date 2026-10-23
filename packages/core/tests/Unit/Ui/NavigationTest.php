<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasNavigation;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
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
        ->and($urls['rules'])->toBe(route('rules'))
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
    Route::get('/later/{any}', fn () => 'x')->middleware('web');
    app(ModuleRegistry::class)->register(brokenNavModule('later', [
        ['key' => 'later', 'label' => 'Later', 'icon' => 'x', 'route' => 'later.not-yet', 'path' => '/later', 'order' => 5],
    ]));

    $items = app(Navigation::class)->items(null, navRequest('/later/abc'));

    expect(array_column($items, 'active', 'key')['later'])->toBeTrue();
});

/** Anonymous module with the given navigation entries. */
function brokenNavModule(string $name, array $entries): ModuleServiceProvider
{
    return new class(app(), $name, $entries) extends ModuleServiceProvider implements HasNavigation
    {
        public function __construct($app, private readonly string $moduleName, private readonly array $entries)
        {
            parent::__construct($app);
        }

        public function name(): string
        {
            return $this->moduleName;
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function navigation(): array
        {
            return $this->entries;
        }

        protected function moduleRoot(): string
        {
            return sys_get_temp_dir().'/ladcms-nav-'.$this->moduleName;
        }
    };
}

it('skips malformed and unroutable module entries with a single warning each', function () {
    Log::shouldReceive('warning')->times(3);
    Route::get('/needs-param/{id}', fn () => 'x')->name('needs.param');

    app(ModuleRegistry::class)->register(brokenNavModule('shaky', [
        ['label' => 'No key', 'icon' => 'x', 'route' => 'home', 'order' => 1],
        ['key' => 'ghost', 'label' => 'Ghost', 'icon' => 'x', 'route' => 'nope.nowhere', 'order' => 2],
        ['key' => 'param', 'label' => 'Param', 'icon' => 'x', 'route' => 'needs.param', 'order' => 3],
        ['key' => 'fine', 'label' => 'Fine', 'icon' => 'x', 'route' => 'home', 'order' => 4],
    ]));

    $nav = app(Navigation::class);
    $keys = array_column($nav->items(null, navRequest('/')), 'key');
    $nav->items(null, navRequest('/'));

    expect($keys)->toContain('fine', 'home')->not->toContain('ghost', 'param');
});

it('sorts equal orders by key', function () {
    app(ModuleRegistry::class)->register(brokenNavModule('ties', [
        ['key' => 'zeta', 'label' => 'Z', 'icon' => 'x', 'route' => 'home', 'order' => 5],
        ['key' => 'alpha', 'label' => 'A', 'icon' => 'x', 'route' => 'home', 'order' => 5],
    ]));

    $keys = array_column(app(Navigation::class)->items(null, navRequest('/')), 'key');

    expect(array_slice($keys, array_search('alpha', $keys), 2))->toBe(['alpha', 'zeta']);
});
