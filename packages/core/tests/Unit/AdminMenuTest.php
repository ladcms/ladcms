<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Admin\AdminMenu;
use Ladcms\Core\Admin\MenuItem;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

function requestFor(string $uri): Request
{
    $request = Request::create($uri);
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

/** @param list<MenuItem> $items */
function menuItem(array $items, string $key): ?MenuItem
{
    return collect($items)->first(fn (MenuItem $i) => $i->key === $key);
}

it('shows all core sections to the creator', function () {
    $creator = User::factory()->create()->assignRole('creator');

    $menu = app(AdminMenu::class)->build($creator, requestFor('/admin'));

    expect(collect($menu)->pluck('key')->all())
        ->toBe(['dashboard', 'users', 'roles', 'moderation', 'settings', 'modules', 'activity']);

    $dashboard = menuItem($menu, 'dashboard');
    expect($dashboard->icon)->toBe('layout-dashboard')
        ->and($dashboard->route)->toBe('admin.dashboard')
        ->and($dashboard->url)->toBe(url('/admin'))
        ->and($dashboard->label)->toBe(__('core::admin.menu.dashboard'));

    // Роуты разделов появятся в следующих задачах — url собирается из известного пути.
    expect(menuItem($menu, 'users')->url)->toBe(url('/admin/users'))
        ->and(collect(menuItem($menu, 'users')->children)->pluck('key')->all())->toBe(['all', 'suspicious'])
        ->and(collect(menuItem($menu, 'moderation')->children)->pluck('key')->all())->toBe(['complaints', 'bans']);
});

it('filters sections by permission', function () {
    $mod = User::factory()->create()->assignRole('moderator');

    $keys = collect(app(AdminMenu::class)->build($mod, requestFor('/admin')))->pluck('key')->all();

    expect($keys)->toBe(['dashboard', 'users', 'moderation']);
});

it('lists settings groups of all HasSettings modules as children', function () {
    $creator = User::factory()->create()->assignRole('creator');

    $settings = menuItem(app(AdminMenu::class)->build($creator, requestFor('/admin')), 'settings');
    $children = collect($settings->children);

    expect($children->pluck('key')->all())->toContain('general', 'registration', 'moderation', 'appearance')
        ->and($children->firstWhere('key', 'general')->url)->toBe(url('/admin/settings/general'));
});

it('adds sections of modules implementing HasAdminPanel', function () {
    $this->app->register(DummyServiceProvider::class);
    $creator = User::factory()->create()->assignRole('creator');

    $menu = app(AdminMenu::class)->build($creator, requestFor('/admin'));
    $dummy = menuItem($menu, 'dummy');

    expect($dummy)->not->toBeNull()
        ->and(collect($menu)->pluck('key')->last())->toBe('dummy')
        ->and($dummy->children)->toHaveCount(1)
        ->and($dummy->children[0]->route)->toBe('admin.dummy.index')
        ->and($dummy->children[0]->icon)->toBe('box')
        ->and($dummy->children[0]->label)->toBe(__('dummy::messages.admin'))
        ->and($dummy->children[0]->url)->toBe(route('admin.dummy.index'));
});

it('marks the current route as active', function () {
    $this->app->register(DummyServiceProvider::class);
    $creator = User::factory()->create()->assignRole('creator');

    $menu = app(AdminMenu::class)->build($creator, requestFor('/admin/dummy'));

    expect(menuItem($menu, 'dummy')->active)->toBeTrue()
        ->and(menuItem($menu, 'dummy')->children[0]->active)->toBeTrue()
        ->and(menuItem($menu, 'dashboard')->active)->toBeFalse();

    $menu = app(AdminMenu::class)->build($creator, requestFor('/admin'));
    expect(menuItem($menu, 'dashboard')->active)->toBeTrue()
        ->and(menuItem($menu, 'dummy')->active)->toBeFalse();
});

it('shows the pending complaints badge on moderation', function () {
    $mod = User::factory()->create()->assignRole('moderator');
    $author = User::factory()->create();
    Complaint::query()->create([
        'user_id' => $author->id,
        'target_user_id' => $mod->id,
        'reason_code' => 'spam',
        'comment' => 'spam',
    ]);

    $moderation = menuItem(app(AdminMenu::class)->build($mod, requestFor('/admin')), 'moderation');

    expect($moderation->badge)->toBe(1)
        ->and($moderation->toArray())->toHaveKeys(['key', 'label', 'icon', 'route', 'url', 'order', 'permission', 'badge', 'active', 'children']);
});
