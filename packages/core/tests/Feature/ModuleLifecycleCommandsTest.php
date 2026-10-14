<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Tests\Fixtures\BrokenModule\BrokenServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\FriendModule\FriendServiceProvider;
use Ladcms\Core\Tests\Fixtures\NeedsFriendModule\NeedsFriendServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
    $this->packageJson = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($this->packageJson, '{"dependencies":{}}');
    config()->set('core.package_json', $this->packageJson);
    $this->artisan('ladcms:install', ['module' => 'dummy'])->assertSuccessful();
});

afterEach(fn () => @unlink($this->packageJson));

it('updates installed modules', function () {
    $this->artisan('ladcms:update')->expectsOutputToContain('dummy 1.0.0')->assertSuccessful();
});

it('uninstalls a module after confirmation', function () {
    $this->artisan('ladcms:uninstall', ['module' => 'dummy'])
        ->expectsConfirmation('Roll back migrations of [dummy]? Data will be lost.', 'no')
        ->expectsOutputToContain('Module dummy uninstalled')
        ->assertSuccessful();
});

it('reports why a failed module cannot be installed', function () {
    $this->app->register(BrokenServiceProvider::class);

    $this->artisan('ladcms:install', ['module' => 'broken'])
        ->expectsOutputToContain('Module [broken] cannot be installed: requires core ^9.0')
        ->assertExitCode(1);
});

it('refuses to uninstall core without asking', function () {
    $this->artisan('ladcms:uninstall', ['module' => 'core'])
        ->expectsOutputToContain('The core module cannot be uninstalled.')
        ->doesntExpectOutputToContain('Roll back migrations')
        ->assertExitCode(1);
});

it('refuses to uninstall a module that others depend on', function () {
    $this->app->register(FriendServiceProvider::class);
    $this->app->register(NeedsFriendServiceProvider::class);
    $this->artisan('ladcms:install', ['module' => 'friend'])->assertSuccessful();
    $this->artisan('ladcms:install', ['module' => 'needs-friend'])->assertSuccessful();

    $this->artisan('ladcms:uninstall', ['module' => 'friend'])
        ->expectsOutputToContain('Module [friend] is required by: needs-friend')
        ->assertExitCode(1);
});

it('uninstalls a registered module that failed validation', function () {
    $this->app->register(BrokenServiceProvider::class);
    Module::create(['name' => 'broken', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);

    $this->artisan('ladcms:uninstall', ['module' => 'broken'])
        ->expectsConfirmation('Roll back migrations of [broken]? Data will be lost.', 'no')
        ->expectsOutputToContain('Module broken uninstalled')
        ->assertSuccessful();
    expect(Module::where('name', 'broken')->exists())->toBeFalse();
});

it('fails for an unknown module without asking for confirmation', function () {
    $this->artisan('ladcms:uninstall', ['module' => 'nope'])
        ->expectsOutputToContain('Module [nope] is not registered')
        ->doesntExpectOutputToContain('Roll back migrations')
        ->assertExitCode(1);
});
