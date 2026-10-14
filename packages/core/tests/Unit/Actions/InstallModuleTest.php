<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Ladcms\Core\Actions\Modules\InstallModule;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Module\ModuleNotFoundException;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
    $this->packageJson = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($this->packageJson, '{"dependencies":{}}');
    config()->set('core.package_json', $this->packageJson);
});

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

it('fails for unknown module without side effects', function () {
    expect(fn () => app(InstallModule::class)->handle('nope'))->toThrow(ModuleNotFoundException::class)
        ->and(Module::count())->toBe(0);
});
