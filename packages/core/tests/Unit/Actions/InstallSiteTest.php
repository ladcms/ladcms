<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Install\InstallSite;
use Ladcms\Core\Actions\Install\SiteAlreadyInstalledException;
use Ladcms\Core\Actions\Install\SiteInstallData;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;

uses(RefreshDatabase::class);

function installData(): SiteInstallData
{
    return new SiteInstallData('My Community', 'en', 'owner', 'Owner', 'owner@example.com', 'secret-password-123');
}

it('installs roles, permissions, settings and the creator account', function () {
    $admin = app(InstallSite::class)->handle(installData());

    expect(Role::count())->toBe(8)
        ->and($admin->hasRole('creator'))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(app(GeneralSettings::class)->site_name)->toBe('My Community')
        ->and(app(GeneralSettings::class)->default_language)->toBe('en')
        ->and(Module::where('name', 'core')->exists())->toBeTrue();
});

it('refuses to run twice without force', function () {
    app(InstallSite::class)->handle(installData());

    expect(fn () => app(InstallSite::class)->handle(installData()))->toThrow(SiteAlreadyInstalledException::class)
        ->and(User::count())->toBe(1);
});

it('with force re-seeds without duplicating', function () {
    app(InstallSite::class)->handle(installData());
    app(InstallSite::class)->handle(new SiteInstallData('X', 'ru', 'second', 'S', 's@example.com', 'another-secret-12'), force: true);

    expect(Role::count())->toBe(8)->and(User::count())->toBe(2);
});
