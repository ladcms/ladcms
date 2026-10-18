<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Contracts\InviteValidator;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');
});

it('shares the layout prop on the home page', function () {
    $this->app->register(DummyServiceProvider::class);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('layout.navigation', 4)
        ->where('layout.navigation.0.key', 'home')
        ->where('layout.navigation.0.active', true)
        ->has('layout.header_actions', 1)
        ->has('layout.widgets.sidebar', 2)
        ->has('layout.widgets.main', 1)
        ->where('layout.widgets.main.0.component', 'dummy::Hello'));
});

it('shares main widgets on the home page only', function () {
    $this->app->register(DummyServiceProvider::class);

    Route::get('/other', fn () => Inertia\Inertia::render('core::welcome'))->middleware('web');

    $this->get('/other')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('layout.widgets.sidebar', 2)
        ->where('layout.widgets.main', []));
});

it('does not share the layout on admin routes', function () {
    $creator = User::factory()->create()->assignRole('creator');

    $this->actingAs($creator)->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin')
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('layout'));
});

it('shares site data from settings', function () {
    $general = app(GeneralSettings::class);
    $general->site_name = 'Cool Site';
    $general->site_description = 'About it';
    $general->save();

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('layout.site.name', 'Cool Site')
        ->where('layout.site.description', 'About it')
        ->where('layout.site.logo_url', null));
});

it('computes registration_open from settings and invite validator', function (bool $open, bool $inviteOnly, bool $bound, bool $expected) {
    $registration = app(RegistrationSettings::class);
    $registration->open = $open;
    $registration->invite_only = $inviteOnly;
    $registration->save();

    if ($bound) {
        $this->app->bind(InviteValidator::class, fn () => new class implements InviteValidator
        {
            public function validate(string $code, string $email): bool
            {
                return true;
            }

            public function consume(string $code, User $user): void {}
        });
    }

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('layout.site.registration_open', $expected));
})->with([
    'closed' => [false, false, false, false],
    'open' => [true, false, false, true],
    'invite only without validator' => [true, true, false, false],
    'invite only with validator' => [true, true, true, true],
]);

it('shares public i18n keys on public pages without admin keys', function () {
    $this->app->register(DummyServiceProvider::class);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('i18n', fn ($map) => $map['core::public.nav.home'] === 'Home'
            && ! array_key_exists('core::admin.saved', $map->toArray())));
});

it('shares both public and admin i18n keys on admin routes', function () {
    $creator = User::factory()->create(['language' => 'ru'])->assignRole('creator');

    $this->actingAs($creator)->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('i18n', fn ($map) => $map['core::public.nav.home'] === 'Главная' && $map['core::admin.saved'] === 'Сохранено'));
});
