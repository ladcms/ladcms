<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Ladcms\Core\Actions\Users\UpdateLocale;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

it('stores the language of an authenticated user', function () {
    $user = User::factory()->create(['language' => 'ru']);

    app(UpdateLocale::class)->handle($user, 'en');

    expect($user->fresh()->language)->toBe('en');
});

it('queues a year-long locale cookie for a guest', function () {
    app(UpdateLocale::class)->handle(null, 'en');

    $cookie = Cookie::getQueuedCookies()[0] ?? null;
    expect($cookie?->getName())->toBe('locale')
        ->and($cookie?->getValue())->toBe('en')
        ->and($cookie?->getExpiresTime())->toBeGreaterThan(now()->addDays(364)->getTimestamp());
});

it('rejects an unavailable locale', function () {
    expect(fn () => app(UpdateLocale::class)->handle(null, 'de'))->toThrow(InvalidArgumentException::class);
});
