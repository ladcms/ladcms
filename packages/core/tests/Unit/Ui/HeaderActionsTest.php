<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Models\User;
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
