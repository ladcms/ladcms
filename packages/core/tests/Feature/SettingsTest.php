<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\Settings\DummySettings;

uses(RefreshDatabase::class);

it('seeds default core settings', function () {
    $general = app(GeneralSettings::class);
    $registration = app(RegistrationSettings::class);

    expect($general->site_name)->toBe('LADCMS')
        ->and($general->default_language)->toBe('ru')
        ->and($registration->open)->toBeTrue()
        ->and($registration->readonly_hours)->toBe(0);
});

it('persists updates', function () {
    $general = app(GeneralSettings::class);
    $general->site_name = 'My Site';
    $general->save();

    expect(app(GeneralSettings::class)->refresh()->site_name)->toBe('My Site');
});

it('registers settings classes declared by modules', function () {
    $this->app->register(DummyServiceProvider::class);
    $this->artisan('migrate')->assertSuccessful();

    expect(app(DummySettings::class)->greeting)->toBe('hello from settings');
});
