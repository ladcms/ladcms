<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Ladcms\Core\Actions\Modules\InstallModule;
use Ladcms\Core\Actions\Modules\UninstallModule;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Module\ModuleHasDependentsException;
use Ladcms\Core\Module\ModuleNotFoundException;
use Ladcms\Core\Tests\Fixtures\BrokenModule\BrokenServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\FriendModule\FriendServiceProvider;
use Ladcms\Core\Tests\Fixtures\NeedsFriendModule\NeedsFriendServiceProvider;
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

it('refuses to uninstall core', function () {
    expect(fn () => app(UninstallModule::class)->handle('core'))
        ->toThrow(InvalidArgumentException::class, 'The core module cannot be uninstalled.');
});

it('refuses to uninstall a module other installed modules depend on', function () {
    $this->app->register(FriendServiceProvider::class);
    $this->app->register(NeedsFriendServiceProvider::class);
    app(InstallModule::class)->handle('friend');
    app(InstallModule::class)->handle('needs-friend');

    expect(fn () => app(UninstallModule::class)->handle('friend'))
        ->toThrow(ModuleHasDependentsException::class, 'Module [friend] is required by: needs-friend')
        ->and(Module::where('name', 'friend')->exists())->toBeTrue();
});

it('uninstalls a registered module that failed validation', function () {
    $this->app->register(BrokenServiceProvider::class);
    Module::create(['name' => 'broken', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);

    app(UninstallModule::class)->handle('broken');

    expect(Module::where('name', 'broken')->exists())->toBeFalse();
});

it('fails for an unknown module', function () {
    expect(fn () => app(UninstallModule::class)->handle('nope'))->toThrow(ModuleNotFoundException::class);
});
