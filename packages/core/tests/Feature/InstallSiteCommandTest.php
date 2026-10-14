<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

it('installs the site non-interactively', function () {
    $this->artisan('ladcms:install', [
        '--site-name' => 'Site', '--language' => 'ru', '--admin-login' => 'root', '--admin-name' => 'Root',
        '--admin-email' => 'root@example.com', '--admin-password' => 'root-password-123',
    ])->expectsOutputToContain('LADCMS installed')->assertSuccessful();

    expect(User::where('login', 'root')->exists())->toBeTrue();
});

it('reports an already installed site', function () {
    $this->artisan('ladcms:install', ['--site-name' => 'S', '--admin-login' => 'root', '--admin-name' => 'R', '--admin-email' => 'r@example.com', '--admin-password' => 'root-password-123'])->assertSuccessful();
    $this->artisan('ladcms:install', ['--site-name' => 'S', '--admin-login' => 'root2', '--admin-name' => 'R', '--admin-email' => 'r2@example.com', '--admin-password' => 'root-password-123'])
        ->expectsOutputToContain('already installed')->assertFailed();
});

it('still installs a module when a name is given', function () {
    $this->app->register(DummyServiceProvider::class);
    $path = tempnam(sys_get_temp_dir(), 'pkg');
    file_put_contents($path, '{"dependencies":{}}');
    config()->set('core.package_json', $path);

    $this->artisan('ladcms:install', ['module' => 'dummy'])->expectsOutputToContain('dummy 1.0.0 installed')->assertSuccessful();
    @unlink($path);
});

it('fails in non-interactive mode when required options are missing', function () {
    $this->artisan('ladcms:install', ['--site-name' => 'S', '--no-interaction' => true])
        ->expectsOutputToContain('--admin-login')->assertFailed();

    expect(User::count())->toBe(0);
});
