<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Modules\InstallModule;
use Ladcms\Core\Actions\Modules\UpdateInstalledModules;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

afterEach(fn () => @unlink($this->packageJson));

it('re-installs every enabled module and skips disabled and missing ones', function () {
    $this->app->register(DummyServiceProvider::class);
    $this->packageJson = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($this->packageJson, '{"dependencies":{}}');
    config()->set('core.package_json', $this->packageJson);

    app(InstallModule::class)->handle('dummy');
    Module::where('name', 'dummy')->update(['version' => '0.9.0']);
    Module::create(['name' => 'gone', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);
    Module::create(['name' => 'disabled', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => false]);
    Module::create(['name' => 'core', 'version' => '0.0.1', 'installed_at' => now(), 'is_enabled' => true]);

    $reports = app(UpdateInstalledModules::class)->handle();

    expect(collect($reports)->pluck('module')->all())->toBe(['core', 'dummy'])
        ->and(Module::where('name', 'dummy')->value('version'))->toBe('1.0.0')
        ->and(Module::where('name', 'core')->value('version'))->toBe(CoreServiceProvider::VERSION);
});

it('does not install core on a site that is not installed', function () {
    $this->packageJson = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($this->packageJson, '{"dependencies":{}}');
    config()->set('core.package_json', $this->packageJson);

    expect(app(UpdateInstalledModules::class)->handle())->toBe([])
        ->and(Module::where('name', 'core')->exists())->toBeFalse();
});
