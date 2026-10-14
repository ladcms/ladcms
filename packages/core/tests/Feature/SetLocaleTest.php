<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Models\User;
use Ladcms\Core\Services\LocaleManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('web')->get('/locale-probe', fn () => app()->getLocale());
});

it('uses the language of the authenticated user', function () {
    $user = User::factory()->create(['language' => 'en']);

    $this->actingAs($user)->get('/locale-probe')->assertSee('en');
});

it('uses the locale cookie for a guest', function () {
    $this->withUnencryptedCookie('locale', 'en')->get('/locale-probe')->assertSee('en');
});

it('uses the best supported Accept-Language entry', function () {
    $this->get('/locale-probe', ['Accept-Language' => 'en-US,en;q=0.9'])->assertSee('en');
});

it('falls back to the default language for unsupported ones', function () {
    $this->get('/locale-probe', ['Accept-Language' => 'de'])->assertSee('ru');
});

it('ignores unsupported user language and cookie', function () {
    $user = User::factory()->create(['language' => 'xx']);

    $this->actingAs($user)->withUnencryptedCookie('locale', 'de')->get('/locale-probe', ['Accept-Language' => 'de'])->assertSee('ru');
});

it('exposes available locales and the current one', function () {
    $manager = app(LocaleManager::class);

    expect($manager->available())->toBe(['ru' => 'Русский', 'en' => 'English'])
        ->and($manager->current())->toBe(app()->getLocale());
});
