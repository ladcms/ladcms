<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->register(DummyServiceProvider::class);
    $path = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($path, '{"dependencies":{}}');
    config()->set('core.package_json', $path);
});

it('installs a module and tells the user to rebuild assets', function () {
    $this->artisan('ladcms:install', ['module' => 'dummy'])
        ->expectsOutputToContain('dummy 1.0.0 installed')
        ->expectsOutputToContain('npm install && npm run build')
        ->assertSuccessful();
});

it('reports unknown modules', function () {
    $this->artisan('ladcms:install', ['module' => 'nope'])
        ->expectsOutputToContain('Module [nope] is not registered')
        ->assertFailed();
});
