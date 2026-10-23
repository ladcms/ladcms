<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

it('renders core::home for guests without page-specific widget props', function () {
    $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::home')
        ->missing('widgets')
        ->missing('version'));
});

it('renders core::home for authenticated users', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::home'));
});

it('shares the core Welcome widget in the main slot', function () {
    $general = app(GeneralSettings::class);
    $general->site_name = 'Cool Site';
    $general->save();

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('layout.widgets.main', 1)
        ->where('layout.widgets.main.0.component', 'core::Welcome')
        ->where('layout.widgets.main.0.module', 'core')
        ->where('layout.widgets.main.0.props.site_name', 'Cool Site')
        ->where('layout.widgets.main.0.props.rules_url', route('rules'))
        ->has('layout.widgets.main.0.props.description'));
});

it('filters out main widgets whose component file is missing and keeps those with a file', function () {
    $this->app->register(DummyServiceProvider::class);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('layout.widgets.main', fn ($main): bool => collect($main)->pluck('component')->sort()->values()->all() === ['core::Welcome', 'dummy::Hello']));
});
