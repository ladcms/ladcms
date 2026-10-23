<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\TranslationMap;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

it('builds a flat map of strings only', function () {
    $map = app(TranslationMap::class)->build(Request::create('/'));

    expect($map)->toHaveKey('core::public.nav.home')
        ->and($map)->not->toHaveKey('core::admin.saved')
        ->and(array_filter($map, fn ($value): bool => ! is_string($value)))->toBe([]);
});

it('shares i18n as a plain prop on every request, even when the client sends once-prop headers', function () {
    $user = User::factory()->create(['language' => 'en']);

    $first = $this->actingAs($user)->get('/', ['X-Inertia' => 'true'])->json();
    $second = $this->actingAs($user)->get('/rules', [
        'X-Inertia' => 'true',
        'X-Inertia-Except-Once-Props' => 'i18n',
    ])->json();

    expect($first)->not->toHaveKey('onceProps')
        ->and($first['props']['i18n']['core::public.nav.home'])->toBe('Home')
        ->and($second['props']['i18n']['core::public.nav.home'])->toBe('Home');
});

it('follows the user locale between requests', function () {
    $user = User::factory()->create(['language' => 'ru']);
    expect($this->actingAs($user)->get('/', ['X-Inertia' => 'true'])->json('props.i18n')['core::public.nav.home'])->toBe('Главная');

    $user->update(['language' => 'en']);
    expect($this->actingAs($user->fresh())->get('/', ['X-Inertia' => 'true'])->json('props.i18n')['core::public.nav.home'])->toBe('Home');

    $user->update(['language' => 'ru']);
    expect($this->actingAs($user->fresh())->get('/', ['X-Inertia' => 'true'])->json('props.i18n')['core::public.nav.home'])->toBe('Главная');
});

it('adds admin keys on admin routes only and keeps public keys there', function () {
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');
    $creator = User::factory()->create(['language' => 'en'])->assignRole('creator');
    $admin = $this->actingAs($creator)->withSession(['auth.password_confirmed_at' => time()])->get('/admin', ['X-Inertia' => 'true'])->json('props.i18n');
    $public = $this->actingAs($creator)->get('/', ['X-Inertia' => 'true'])->json('props.i18n');
    $adminAgain = $this->actingAs($creator)->withSession(['auth.password_confirmed_at' => time()])->get('/admin', ['X-Inertia' => 'true'])->json('props.i18n');

    expect($admin)->toHaveKeys(['core::admin.saved', 'core::public.nav.home'])
        ->and($public)->toHaveKey('core::public.nav.home')
        ->and($public)->not->toHaveKey('core::admin.saved')
        ->and($adminAgain)->toHaveKey('core::admin.saved');
});
