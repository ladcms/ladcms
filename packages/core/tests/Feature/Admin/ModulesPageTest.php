<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Fixtures\BrokenModule\BrokenServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\FriendModule\FriendServiceProvider;
use Ladcms\Core\Tests\Fixtures\NeedsFriendModule\NeedsFriendServiceProvider;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function moduleRow(string $name, string $version = '1.0.0', bool $enabled = true): Module
{
    return Module::create(['name' => $name, 'version' => $version, 'installed_at' => now(), 'is_enabled' => $enabled]);
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->admin = User::factory()->create()->assignRole('main_admin');
    $this->moderator = User::factory()->create()->assignRole('moderator');
});

function modulesAs(User $actor): mixed
{
    return test()->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from('/admin/modules');
}

it('lists modules with statuses', function () {
    $this->app->register(DummyServiceProvider::class);
    $this->app->register(BrokenServiceProvider::class);
    moduleRow('core', CoreServiceProvider::VERSION);
    moduleRow('dummy', '0.9.0');
    moduleRow('ghost');

    modulesAs($this->admin)->get('/admin/modules')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('core::admin/modules/index')->has('modules', 4);
            $rows = collect($page->toArray()['props']['modules'])->keyBy('name');

            expect($rows->keys()->all())->toBe(['core', 'broken', 'dummy', 'ghost'])
                ->and($rows['core'])->toMatchArray(['status' => 'active', 'is_core' => true, 'needs_update' => false])
                ->and($rows['dummy'])->toMatchArray(['status' => 'active', 'version' => '1.0.0', 'installed_version' => '0.9.0', 'needs_update' => true, 'is_enabled' => true])
                ->and($rows['broken']['status'])->toBe('failed')
                ->and($rows['broken']['reason'])->toContain('core')
                ->and($rows['ghost'])->toMatchArray(['status' => 'not_installed', 'version' => null, 'installed_version' => '1.0.0']);
        });
});

it('shows a disabled module with the reason', function () {
    moduleRow('dummy', '1.0.0', false);
    $this->app->register(DummyServiceProvider::class);

    modulesAs($this->admin)->get('/admin/modules')
        ->assertInertia(function (AssertableInertia $page) {
            $row = collect($page->toArray()['props']['modules'])->firstWhere('name', 'dummy');

            expect($row)->toMatchArray(['status' => 'disabled', 'is_enabled' => false, 'reason' => 'disabled by administrator']);
        });
});

it('does not boot a module disabled in the database', function () {
    moduleRow('dummy', '1.0.0', false);
    $this->app->register(DummyServiceProvider::class);

    $this->get('/dummy')->assertNotFound();
});

it('toggles a module off, audits it', function () {
    $this->app->register(DummyServiceProvider::class);
    moduleRow('dummy');

    modulesAs($this->admin)->patch('/admin/modules/dummy', ['is_enabled' => false])->assertRedirect('/admin/modules');

    expect(Module::where('name', 'dummy')->value('is_enabled'))->toBeFalse();
    $log = Activity::query()->where('event', 'module.toggled')->sole();
    expect($log->causer_id)->toBe($this->admin->id)->and($log->properties['enabled'])->toBeFalse();
});

it('toggles a module back on', function () {
    moduleRow('dummy', '1.0.0', false);
    $this->app->register(DummyServiceProvider::class);

    modulesAs($this->admin)->patch('/admin/modules/dummy', ['is_enabled' => true])->assertRedirect('/admin/modules');

    expect(Module::where('name', 'dummy')->value('is_enabled'))->toBeTrue();
});

it('refuses to toggle core', function () {
    moduleRow('core');

    modulesAs($this->admin)->patch('/admin/modules/core', ['is_enabled' => false])
        ->assertRedirect('/admin/modules')->assertSessionHasErrors('general');

    expect(Module::where('name', 'core')->value('is_enabled'))->toBeTrue();
});

it('refuses to disable a module with enabled dependents', function () {
    $this->app->register(FriendServiceProvider::class);
    $this->app->register(NeedsFriendServiceProvider::class);
    moduleRow('friend');
    moduleRow('needs-friend');

    modulesAs($this->admin)->patch('/admin/modules/friend', ['is_enabled' => false])
        ->assertSessionHasErrors(['general' => 'Cannot disable [friend]: required by [needs-friend].']);

    expect(Module::where('name', 'friend')->value('is_enabled'))->toBeTrue();
});

it('refuses an unknown module', function () {
    modulesAs($this->admin)->patch('/admin/modules/nope', ['is_enabled' => false])
        ->assertSessionHasErrors('general');
});

it('requires a boolean is_enabled', function () {
    moduleRow('dummy');
    $this->app->register(DummyServiceProvider::class);

    modulesAs($this->admin)->patch('/admin/modules/dummy', [])->assertSessionHasErrors('is_enabled');
});

it('is forbidden without modules.manage', function () {
    modulesAs($this->moderator)->get('/admin/modules')->assertForbidden();
    modulesAs($this->moderator)->patch('/admin/modules/dummy', ['is_enabled' => false])->assertForbidden();
});

it('tells the page whether routes are cached', function () {
    modulesAs($this->admin)->get('/admin/modules')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('routes_cached', false));
});
