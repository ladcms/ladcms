<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasWidgets;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Ui\Widgets;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
});

it('returns core sidebar widgets with module and props', function () {
    User::factory()->count(2)->create();

    $widgets = app(Widgets::class)->for('sidebar', null);

    expect(array_column($widgets, 'name'))->toBe(['online', 'latest-users'])
        ->and($widgets[0]['component'])->toBe('core::Online')
        ->and($widgets[0]['module'])->toBe('core')
        ->and($widgets[0]['props'])->toHaveKeys(['count', 'users'])
        ->and($widgets[1]['props']['users'])->toHaveCount(2);
});

it('filters by slot and evaluates props of an existing module widget', function () {
    Log::shouldReceive('warning')->once();

    $widgets = app(Widgets::class)->for('main', null);

    $widgets = array_values(array_filter($widgets, fn (array $widget): bool => $widget['module'] === 'dummy'));

    expect($widgets)->toHaveCount(1)
        ->and($widgets[0])->toBe(['name' => 'hello', 'component' => 'dummy::Hello', 'module' => 'dummy', 'order' => 10, 'props' => ['greeting' => 'hi']]);
});

it('drops widgets whose component file is missing and warns once per component', function () {
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'dummy::Missing'));

    $widgets = app(Widgets::class);
    $widgets->for('main', null);
    $widgets->for('main', null);
});

it('evaluates props lazily only for the requested slot', function () {
    $calls = new ArrayObject;
    $module = new class($this->app, $calls) extends ModuleServiceProvider implements HasWidgets
    {
        public function __construct($app, private readonly ArrayObject $calls)
        {
            parent::__construct($app);
        }

        public function name(): string
        {
            return 'lazy';
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function widgets(): array
        {
            return [
                ['name' => 'a', 'component' => 'lazy::A', 'slot' => 'sidebar', 'order' => 1, 'props' => function (): array {
                    $this->calls[] = 'sidebar';

                    return [];
                }],
                ['name' => 'b', 'component' => 'lazy::B', 'slot' => 'main', 'order' => 1, 'props' => function (): array {
                    $this->calls[] = 'main';

                    return [];
                }],
            ];
        }

        protected function moduleRoot(): string
        {
            return sys_get_temp_dir().'/ladcms-lazy-'.spl_object_id($this);
        }
    };

    $root = $module->modulePath('resources/js/widgets');
    @mkdir($root, 0777, true);
    touch("{$root}/A.tsx");
    touch("{$root}/B.tsx");
    app(ModuleRegistry::class)->register($module);

    app(Widgets::class)->for('sidebar', null);

    expect($calls->getArrayCopy())->toBe(['sidebar']);

    unlink("{$root}/A.tsx");
    unlink("{$root}/B.tsx");
    rmdir($root);
    rmdir(dirname($root));
    rmdir(dirname($root, 2));
});

it('lists the most recently active online users within the window', function () {
    $online = User::factory()->create(['last_activity_at' => now()->subMinutes(5)]);
    User::factory()->create(['last_activity_at' => now()->subHour()]);
    User::factory()->create(['last_activity_at' => null]);

    $props = app(Widgets::class)->for('sidebar', null)[0]['props'];

    expect($props['count'])->toBe(1)
        ->and($props['users'][0])->toBe(['id' => $online->id, 'login' => $online->login, 'display_name' => $online->name ?: $online->login, 'avatar_url' => $online->avatar_url]);
});

it('caps online users at ten while counting all', function () {
    User::factory()->count(12)->create(['last_activity_at' => now()]);

    $props = app(Widgets::class)->for('sidebar', null)[0]['props'];

    expect($props['count'])->toBe(12)->and($props['users'])->toHaveCount(10);
});

it('lists the five latest registered users', function () {
    User::factory()->count(7)->create();

    $props = app(Widgets::class)->for('sidebar', null)[1]['props'];

    expect($props['users'])->toHaveCount(5)
        ->and($props['users'][0])->toHaveKeys(['id', 'login', 'display_name', 'avatar_url', 'created_at']);
});

/** Anonymous module whose widgets() returns the given entries; touches the listed component files. */
function widgetModule(string $name, array $entries, array $files = []): ModuleServiceProvider
{
    $module = new class(app(), $name, $entries) extends ModuleServiceProvider implements HasWidgets
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

        public function widgets(): array
        {
            return $this->entries;
        }

        protected function moduleRoot(): string
        {
            return File::isDirectory(sys_get_temp_dir()) ? sys_get_temp_dir().'/ladcms-w-'.$this->moduleName : '';
        }
    };

    foreach ($files as $file) {
        @mkdir($module->modulePath('resources/js/widgets'), 0777, true);
        touch($module->modulePath("resources/js/widgets/{$file}.tsx"));
    }
    app(ModuleRegistry::class)->register($module);

    return $module;
}

it('skips a widget with a throwing props closure and warns once', function () {
    Log::shouldReceive('warning')->once();
    widgetModule('boomw', [
        ['name' => 'boom', 'component' => 'boomw::Boom', 'slot' => 'sidebar', 'order' => 1, 'props' => fn () => throw new RuntimeException('nope')],
        ['name' => 'ok', 'component' => 'boomw::Ok', 'slot' => 'sidebar', 'order' => 2],
    ], ['Boom', 'Ok']);

    $widgets = app(Widgets::class);
    $widgets->for('sidebar', null);
    $names = array_column($widgets->for('sidebar', null), 'name');

    expect($names)->toContain('ok')->not->toContain('boom');
});

it('skips malformed widget entries', function () {
    Log::shouldReceive('warning')->once();
    widgetModule('shakyw', [['component' => 'shakyw::A', 'slot' => 'sidebar', 'order' => 1]], ['A']);

    expect(array_column(app(Widgets::class)->for('sidebar', null), 'name'))->not->toContain(null);
});

it('sorts equal orders by name', function () {
    widgetModule('tiesw', [
        ['name' => 'zeta', 'component' => 'tiesw::Z', 'slot' => 'main', 'order' => 5],
        ['name' => 'alpha', 'component' => 'tiesw::A', 'slot' => 'main', 'order' => 5],
    ], ['Z', 'A']);
    Log::shouldReceive('warning')->atLeast()->once();

    $names = array_column(app(Widgets::class)->for('main', null), 'name');

    expect($names)->toBe(['alpha', 'zeta', 'hello', 'welcome']);
});
