<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Ladcms\Core\Actions\Modules\InstallModule;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Module\ModuleFailedException;
use Ladcms\Core\Module\ModuleNotFoundException;
use Ladcms\Core\Tests\Fixtures\BrokenModule\BrokenServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
    $this->packageJson = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($this->packageJson, '{"dependencies":{}}');
    config()->set('core.package_json', $this->packageJson);
});

afterEach(fn () => @unlink($this->packageJson));

it('installs a module: migrations, permissions, npm deps, record', function () {
    $report = app(InstallModule::class)->handle('dummy');

    expect(Schema::hasTable('dummy_items'))->toBeTrue()
        ->and($report->createdPermissions)->toBe(['dummy.view'])
        ->and($report->addedNpmDependencies)->toBe(['left-pad' => '^1.3'])
        ->and(Module::where('name', 'dummy')->value('version'))->toBe('1.0.0');
});

it('is idempotent', function () {
    app(InstallModule::class)->handle('dummy');
    $report = app(InstallModule::class)->handle('dummy');

    expect($report->createdPermissions)->toBe([])
        ->and($report->addedNpmDependencies)->toBe([])
        ->and(Module::count())->toBe(1);
});

it('keeps installed_at and is_enabled on re-install, updating only the version', function () {
    app(InstallModule::class)->handle('dummy');
    $past = now()->subYear()->startOfSecond();
    Module::where('name', 'dummy')->update(['installed_at' => $past, 'is_enabled' => false, 'version' => '0.9.0']);

    app(InstallModule::class)->handle('dummy');

    $record = Module::where('name', 'dummy')->sole();
    expect($record->installed_at->equalTo($past))->toBeTrue()
        ->and($record->is_enabled)->toBeFalse()
        ->and($record->version)->toBe('1.0.0');
});

it('refuses to install a registered module that failed validation', function () {
    $this->app->register(BrokenServiceProvider::class);

    expect(fn () => app(InstallModule::class)->handle('broken'))
        ->toThrow(ModuleFailedException::class, 'Module [broken] cannot be installed: requires core ^9.0')
        ->and(Module::count())->toBe(0);
});

it('fails for unknown module without side effects', function () {
    expect(fn () => app(InstallModule::class)->handle('nope'))->toThrow(ModuleNotFoundException::class)
        ->and(Module::count())->toBe(0);
});
