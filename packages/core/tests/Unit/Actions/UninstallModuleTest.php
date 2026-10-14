<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Ladcms\Core\Actions\Modules\InstallModule;
use Ladcms\Core\Actions\Modules\UninstallModule;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
    $this->packageJson = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($this->packageJson, '{"dependencies":{}}');
    config()->set('core.package_json', $this->packageJson);
    app(InstallModule::class)->handle('dummy');
});

afterEach(fn () => @unlink($this->packageJson));

it('removes permissions and the record, keeping tables by default', function () {
    app(UninstallModule::class)->handle('dummy');

    expect(Module::where('name', 'dummy')->exists())->toBeFalse()
        ->and(Permission::where('name', 'dummy.view')->exists())->toBeFalse()
        ->and(Schema::hasTable('dummy_items'))->toBeTrue();
});

it('rolls back module migrations when asked', function () {
    app(UninstallModule::class)->handle('dummy', rollbackMigrations: true);

    expect(Schema::hasTable('dummy_items'))->toBeFalse();
});
