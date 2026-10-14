<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

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
