<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('web')->get('/locale-probe', fn () => app()->getLocale());
});

it('stores a guest locale in a cookie that the next request resolves', function () {
    $response = $this->from('/')->post(route('locale.update'), ['locale' => 'en'])->assertRedirect('/');

    $cookie = $response->getCookie('locale', false);
    expect($cookie)->not->toBeNull()->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(364)->getTimestamp());

    $this->flushSession();
    $this->withUnencryptedCookie('locale', (string) $cookie->getValue())->get('/locale-probe', ['Accept-Language' => 'ru'])->assertSee('en');
});

it('updates the language of an authenticated user and the next request uses it', function () {
    $user = User::factory()->create(['language' => 'ru']);

    $this->actingAs($user)->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();

    expect($user->fresh()->language)->toBe('en');
    $this->actingAs($user->fresh())->get('/locale-probe')->assertSee('en');
});

it('rejects an unavailable locale with 422', function () {
    $user = User::factory()->create(['language' => 'ru']);

    $this->postJson(route('locale.update'), ['locale' => 'de'])->assertUnprocessable()->assertJsonValidationErrors('locale');
    $this->actingAs($user)->postJson(route('locale.update'), ['locale' => 'xx'])->assertUnprocessable();
    $this->postJson(route('locale.update'), [])->assertUnprocessable();

    expect($user->fresh()->language)->toBe('ru');
});

it('is throttled to 30 requests per minute', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
    }

    $this->post(route('locale.update'), ['locale' => 'en'])->assertStatus(429);
});
