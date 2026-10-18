<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Modules\ToggleModule;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\FriendModule\FriendServiceProvider;
use Ladcms\Core\Tests\Fixtures\NeedsFriendModule\NeedsFriendServiceProvider;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('main_admin');
    $this->app->register(DummyServiceProvider::class);
    Module::create(['name' => 'dummy', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);
});

it('requires modules.manage', function () {
    $user = User::factory()->create()->assignRole('moderator');

    app(ToggleModule::class)->handle($user, 'dummy', false);
})->throws(AuthorizationException::class);

it('disables and enables a module and audits both', function () {
    $module = app(ToggleModule::class)->handle($this->admin, 'dummy', false);

    expect($module->is_enabled)->toBeFalse()
        ->and(Module::where('name', 'dummy')->value('is_enabled'))->toBeFalse();

    app(ToggleModule::class)->handle($this->admin, 'dummy', true);

    expect(Module::where('name', 'dummy')->value('is_enabled'))->toBeTrue()
        ->and(Activity::query()->where('event', 'module.toggled')->count())->toBe(2);
});

it('never toggles core', function () {
    app(ToggleModule::class)->handle($this->admin, 'core', false);
})->throws(InvalidArgumentException::class, 'core');

it('rejects an unknown module', function () {
    app(ToggleModule::class)->handle($this->admin, 'nope', true);
})->throws(InvalidArgumentException::class);

it('names enabled dependents when disabling', function () {
    $this->app->register(FriendServiceProvider::class);
    $this->app->register(NeedsFriendServiceProvider::class);
    Module::create(['name' => 'friend', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);

    app(ToggleModule::class)->handle($this->admin, 'friend', false);
})->throws(InvalidArgumentException::class, 'needs-friend');

it('toggles a module that exists only in the table', function () {
    Module::create(['name' => 'ghost', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);

    expect(app(ToggleModule::class)->handle($this->admin, 'ghost', false)->is_enabled)->toBeFalse();
});
