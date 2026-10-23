<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasProfileActions;
use Ladcms\Core\Module\Contracts\HasProfileSections;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Ui\ProfileSections;

uses(RefreshDatabase::class);

/** Модуль без файлов компонентов: проверяем только агрегацию и порядок. */
function profileModule(string $name, array $sections = [], array $actions = []): ModuleServiceProvider
{
    return new class(app(), $name, $sections, $actions) extends ModuleServiceProvider implements HasProfileActions, HasProfileSections
    {
        public function __construct($app, private readonly string $moduleName, private readonly array $sections, private readonly array $actions)
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

        public function coreVersion(): string
        {
            return '^0.1';
        }

        public function profileSections(): array
        {
            return $this->sections;
        }

        public function profileActions(): array
        {
            return $this->actions;
        }

        protected function moduleRoot(): string
        {
            return dirname(__DIR__, 2).'/Fixtures/DummyModule';
        }
    };
}

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
});

it('puts overview first and keys module tabs by module name', function () {

    $tabs = app(ProfileSections::class)->tabs(User::factory()->create(), null);

    expect(array_column($tabs, 'key'))->toBe(['overview', 'dummy'])
        ->and($tabs[0]['component'])->toBe('core::Overview')
        ->and($tabs[1]['label'])->toBe('Dummy tab');
});

it('translates tab labels that are translation keys and falls back to the raw label', function () {

    $tabs = app(ProfileSections::class)->tabs(User::factory()->create(), null);

    expect($tabs[0]['label'])->toBe(__('core::public.profile.tabs.overview'))
        ->and($tabs[0]['label'])->not->toContain('core::');
});

it('orders overview blocks by order then key', function () {
    $this->app->make(ModuleRegistry::class)->register(profileModule('zeta', ['overview' => ['component' => 'dummy::Block', 'order' => 5]]));
    $this->app->make(ModuleRegistry::class)->register(profileModule('alpha', ['overview' => ['component' => 'dummy::Block', 'order' => 10]]));

    $blocks = app(ProfileSections::class)->overview(User::factory()->create(), null);

    expect(array_column($blocks, 'key'))->toBe(['zeta', 'alpha', 'dummy']);
});

it('hides actions from guests and shows them to users', function () {
    Log::shouldReceive('warning')->atLeast()->once();
    $sections = app(ProfileSections::class);
    $target = User::factory()->create();

    expect($sections->actions($target, null))->toBe([])
        ->and(array_column($sections->actions($target, User::factory()->create()), 'component'))->toBe(['dummy::Wave']);
});

it('respects the permission of an action', function () {
    Log::shouldReceive('warning')->atLeast()->once();
    $this->app->make(ModuleRegistry::class)->register(profileModule('gated', [], [
        ['label' => 'Gated', 'icon' => 'lock', 'component' => 'dummy::Wave', 'order' => 1, 'permission' => 'users.ban'],
    ]));
    $sections = app(ProfileSections::class);
    $target = User::factory()->create();

    expect(array_column($sections->actions($target, User::factory()->create()), 'key'))->toBe(['dummy:Wave']);
});

it('collects counters and ignores a failing counters closure', function () {
    Log::shouldReceive('warning')->atLeast()->once();
    $this->app->make(ModuleRegistry::class)->register(profileModule('broken', ['counters' => fn () => throw new RuntimeException('boom')]));

    $counters = app(ProfileSections::class)->counters(User::factory()->create(), null);

    expect($counters)->toBe([['key' => 'dummy-posts', 'label' => 'Posts', 'value' => 7, 'tab' => 'dummy']]);
});

it('returns tab data only for a known module tab', function () {
    $sections = app(ProfileSections::class);
    $user = User::factory()->create();

    expect($sections->tabData($user, null, 'dummy'))->toBe(['marker' => 'dummy-tab-data', 'user_id' => $user->id])
        ->and($sections->hasModuleTab($user, null, 'dummy'))->toBeTrue()
        ->and($sections->hasModuleTab($user, null, 'ghost'))->toBeFalse()
        ->and($sections->hasModuleTab($user, null, 'overview'))->toBeFalse()
        ->and($sections->tabData($user, null, 'ghost'))->toBe([]);
});

it('hides actions on the viewer own profile', function () {
    $user = User::factory()->create();

    expect(app(ProfileSections::class)->actions($user, $user))->toBe([]);
});

it('calls profileSections() once per module per request', function () {
    DummyServiceProvider::$sectionsCalls = 0;
    $sections = app(ProfileSections::class);
    $user = User::factory()->create();

    $sections->tabs($user, null);
    $sections->overview($user, null);
    $sections->counters($user, null);
    $sections->hasModuleTab($user, null, 'dummy');

    expect(DummyServiceProvider::$sectionsCalls)->toBe(1);
});

it('passes the viewer to counters closures', function () {
    $seen = new ArrayObject;
    app(ModuleRegistry::class)->register(profileModule('spy', ['counters' => function (User $target, ?User $viewer) use ($seen): array {
        $seen[] = $viewer?->id;

        return [];
    }]));
    $viewer = User::factory()->create();

    app(ProfileSections::class)->counters(User::factory()->create(), $viewer);

    expect($seen->getArrayCopy())->toBe([$viewer->id]);
});

it('skips tabs, overview blocks and actions with a non-string component or label instead of failing', function () {
    Log::shouldReceive('warning')->atLeast()->once();
    $registry = $this->app->make(ModuleRegistry::class);
    $registry->register(profileModule('badcomponent', [
        'tab' => ['label' => 'Bad', 'component' => 42],
        'overview' => ['component' => ['dummy::Block'], 'order' => 1],
    ], [
        ['label' => 'Bad', 'icon' => 'x', 'component' => 7, 'order' => 1],
    ]));
    $registry->register(profileModule('badlabel', [
        'tab' => ['label' => ['nope'], 'component' => 'dummy::Tab'],
    ], [
        ['label' => 123, 'icon' => 'x', 'component' => 'dummy::Wave', 'order' => 1],
    ]));
    $sections = app(ProfileSections::class);
    $target = User::factory()->create();

    expect(array_column($sections->tabs($target, null), 'key'))->toBe(['overview', 'dummy'])
        ->and(array_column($sections->overview($target, null), 'key'))->not->toContain('badcomponent')
        ->and(array_column($sections->actions($target, User::factory()->create()), 'key'))->toBe(['dummy:Wave']);
});
