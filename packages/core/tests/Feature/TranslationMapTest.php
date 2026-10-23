<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

it('shares i18n as a once prop keyed by locale and scope', function () {
    $user = User::factory()->create(['language' => 'en']);

    $page = $this->actingAs($user)->get('/', ['X-Inertia' => 'true'])->json();
    $key = array_key_first($page['onceProps'] ?? []);

    expect($key)->toStartWith('i18n:en:public:')
        ->and($page['onceProps'][$key]['prop'])->toBe('i18n')
        ->and($page['props']['i18n']['core::public.nav.home'])->toBe('Home');

    $user->update(['language' => 'ru']);
    $ru = $this->actingAs($user->fresh())->get('/', ['X-Inertia' => 'true'])->json();

    expect(array_key_first($ru['onceProps']))->toStartWith('i18n:ru:public:')
        ->and($ru['props']['i18n']['core::public.nav.home'])->toBe('Главная');
});

it('does not resend the once prop when the client already has it', function () {
    $user = User::factory()->create(['language' => 'en']);
    $first = $this->actingAs($user)->get('/', ['X-Inertia' => 'true'])->json();
    $key = array_key_first($first['onceProps']);

    $second = $this->actingAs($user)->get('/rules', [
        'X-Inertia' => 'true',
        'X-Inertia-Except-Once-Props' => $key,
    ])->json();

    expect($second['props'])->not->toHaveKey('i18n');
});
