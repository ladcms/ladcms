<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Tests\Fixtures\BadConstraintModule\BadConstraintServiceProvider;
use Ladcms\Core\Tests\Fixtures\BrokenModule\BrokenServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\FriendModule\FriendServiceProvider;
use Ladcms\Core\Tests\Fixtures\NeedsFriendModule\NeedsFriendServiceProvider;

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
    $this->app->register(BrokenServiceProvider::class);
    $this->app->register(NeedsFriendServiceProvider::class);
    $this->app->make(ModuleRegistry::class)->validate();
});

it('registers core itself as a module', function () {
    expect(app(ModuleRegistry::class)->has('core'))->toBeTrue();
});

it('registers a module and exposes it by name', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->has('dummy'))->toBeTrue()
        ->and($registry->get('dummy')->version())->toBe('1.0.0')
        ->and($registry->names())->toContain('core', 'dummy');
});

it('loads module config, translations, routes and migrations', function () {
    expect(config('dummy.greeting'))->toBe('hello')
        ->and(__('dummy::messages.hi'))->toBe('Hi from dummy');

    $this->get('/dummy')->assertOk()->assertSee('dummy web');
    expect(Schema::hasTable('dummy_items'))->toBeFalse(); // миграции грузятся, но не применяются автоматически

    $this->artisan('migrate')->assertSuccessful();
    expect(Schema::hasTable('dummy_items'))->toBeTrue();
});

it('wraps admin routes with the admin prefix and middleware', function () {
    $route = collect(Route::getRoutes())->first(fn ($r) => $r->uri() === 'admin/dummy');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('web', 'auth', 'admin');
});

it('does not boot a module whose core version is incompatible', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->has('broken'))->toBeFalse()
        ->and($registry->failures())->toHaveKey('broken')
        ->and($registry->failures()['broken']->reason)->toContain('core');
    $this->get('/dummy')->assertOk(); // приложение живо
});

it('does not crash on a malformed core version constraint', function () {
    $this->app->register(BadConstraintServiceProvider::class);
    $registry = app(ModuleRegistry::class);

    expect($registry->has('bad-constraint'))->toBeFalse()
        ->and($registry->failures()['bad-constraint']->reason)->toContain('invalid core version constraint [1.x-foo]');
    $this->get('/dummy')->assertOk();
});

it('reports registered-but-failed modules and dependents', function () {
    $this->app->register(FriendServiceProvider::class);
    $registry = app(ModuleRegistry::class);

    expect($registry->isRegistered('broken'))->toBeTrue()
        ->and($registry->getRegistered('broken'))->toBeInstanceOf(BrokenServiceProvider::class)
        ->and($registry->get('broken'))->toBeNull()
        ->and($registry->isRegistered('nope'))->toBeFalse()
        ->and($registry->dependents('friend'))->toBe([]); // needs-friend пропущен при boot, значит невалиден
});

it('does not load resources of a module that fails validation', function () {
    $this->get('/broken')->assertNotFound();
    expect(config('broken.flag'))->toBeNull();
});

it('does not boot a module whose dependency is missing', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->has('needs-friend'))->toBeFalse()
        ->and($registry->failures()['needs-friend']->reason)->toContain('friend');
});

it('keeps a module skipped at boot unavailable even if its dependency appears later', function () {
    // needs-friend был пропущен при boot в beforeEach; friend регистрируется уже после boot.
    $this->app->register(FriendServiceProvider::class);
    $registry = app(ModuleRegistry::class);

    expect($registry->has('friend'))->toBeTrue()
        ->and($registry->has('needs-friend'))->toBeFalse()
        ->and($registry->names())->not->toContain('needs-friend')
        ->and($registry->failures()['needs-friend']->reason)->toStartWith('skipped at boot: ')->toContain('friend');
});

it('collects modules implementing an interface', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->implementing(HasPermissions::class)->keys()->all())
        ->toBe(['dummy']);
});
