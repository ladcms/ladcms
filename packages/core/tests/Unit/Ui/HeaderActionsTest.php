<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasHeaderActions;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Ui\HeaderActions;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
});

it('hides auth-only actions from guests but keeps public module actions', function () {
    $items = app(HeaderActions::class)->items(null);

    expect(array_column($items, 'key'))->toBe(['dummy']);
});

it('shows the notification bell to authenticated users, sorted by order', function () {
    $items = app(HeaderActions::class)->items(User::factory()->create());

    expect(array_column($items, 'key'))->toBe(['notifications', 'dummy'])
        ->and($items[0])->toBe(['key' => 'notifications', 'icon' => 'bell', 'component' => 'core::NotificationBell', 'order' => 10])
        ->and($items[1]['component'])->toBe('dummy::Action');
});

it('drops header actions whose component file is missing and warns once', function () {
    Log::shouldReceive('warning')->once()->withArgs(fn (string $m): bool => str_contains($m, 'dummy::MissingAction'));

    $actions = app(HeaderActions::class);
    $actions->items(null);

    expect(array_column($actions->items(null), 'key'))->toBe(['dummy']);
});

it('skips malformed header actions instead of failing', function () {
    $module = new class(app()) extends ModuleServiceProvider implements HasHeaderActions
    {
        public function name(): string
        {
            return 'shaky';
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function headerActions(): array
        {
            return [['icon' => 'x', 'component' => 'shaky::A', 'order' => 1]];
        }
    };
    app(ModuleRegistry::class)->register($module);
    Log::shouldReceive('warning')->atLeast()->once();

    expect(array_column(app(HeaderActions::class)->items(null), 'key'))->toBe(['dummy']);
});

it('sorts equal orders by key', function () {
    $module = new class(app()) extends ModuleServiceProvider implements HasHeaderActions
    {
        public function name(): string
        {
            return 'ties';
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function headerActions(): array
        {
            return [
                ['key' => 'zeta', 'icon' => 'x', 'component' => 'dummy::Action', 'order' => 5],
                ['key' => 'alpha', 'icon' => 'x', 'component' => 'dummy::Action', 'order' => 5],
            ];
        }
    };
    app(ModuleRegistry::class)->register($module);

    expect(array_column(app(HeaderActions::class)->items(null), 'key'))->toBe(['alpha', 'zeta', 'dummy']);
});

it('skips header actions with a non-string component instead of failing', function () {
    $module = new class(app()) extends ModuleServiceProvider implements HasHeaderActions
    {
        public function name(): string
        {
            return 'typed';
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function headerActions(): array
        {
            return [['key' => 'typed', 'icon' => 'x', 'component' => 42, 'order' => 1]];
        }
    };
    app(ModuleRegistry::class)->register($module);
    Log::shouldReceive('warning')->atLeast()->once();

    expect(array_column(app(HeaderActions::class)->items(null), 'key'))->toBe(['dummy']);
});
