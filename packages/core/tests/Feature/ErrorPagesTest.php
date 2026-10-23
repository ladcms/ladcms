<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Ladcms\Core\Http\ErrorPageRenderer;
use Ladcms\Core\Models\User;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

uses(RefreshDatabase::class);

function renderError(int $status, ?Request $request = null): SymfonyResponse
{
    $request ??= Request::create('/missing');
    $request->headers->set('Accept', 'text/html');
    $request->headers->set('X-Inertia', 'true'); // JSON-форма страницы вместо HTML-оболочки

    return app(ErrorPageRenderer::class)->render(new Response('original', $status), new RuntimeException('boom'), $request);
}

/** @return array{component: string, props: array<string, mixed>} */
function errorPage(SymfonyResponse $response): array
{
    return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
}

it('renders core::error for 403, 404, 405 and 503 with the right status', function (int $status) {
    $response = renderError($status);

    expect($response->getStatusCode())->toBe($status);
    $page = errorPage($response);
    expect($page['component'])->toBe('core::error')
        ->and($page['props']['status'])->toBe($status)
        ->and($page['props']['title'])->not->toBe("core::public.errors.{$status}.title")
        ->and($page['props']['message'])->toBeString();
})->with([403, 404, 405, 503]);

it('renders core::error for 500 in production', function () {
    config(['app.debug' => false]);

    $response = renderError(500);

    expect($response->getStatusCode())->toBe(500)
        ->and(errorPage($response)['component'])->toBe('core::error');
});

it('keeps the original response for 500 in debug mode', function () {
    config(['app.debug' => true]);

    $response = renderError(500);

    expect($response->getContent())->toBe('original');
});

it('flashes a toast and redirects back on 419', function () {
    $response = renderError(419);

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getStatusCode())->toBe(302);
});

it('keeps the original response for JSON requests and other statuses', function () {
    $json = Request::create('/missing', server: ['HTTP_ACCEPT' => 'application/json']);
    $original = new JsonResponse(['message' => 'Not Found'], 404);

    expect(app(ErrorPageRenderer::class)->render($original, new RuntimeException, $json))->toBe($original);

    $teapot = new Response('tea', 418);
    expect(app(ErrorPageRenderer::class)->render($teapot, new RuntimeException, Request::create('/x')))->toBe($teapot);
});

it('has translated titles and messages for every handled status in both locales', function () {
    foreach (['ru', 'en'] as $locale) {
        foreach ([403, 404, 405, 419, 500, 503] as $status) {
            expect(trans("core::public.errors.{$status}.title", [], $locale))->not->toStartWith('core::public');
            expect(trans("core::public.errors.{$status}.message", [], $locale))->not->toStartWith('core::public');
        }
    }
});

it('shares the public layout on a 404 for an unknown URL', function () {
    $response = renderError(404, Request::create('/definitely-missing'));

    expect(errorPage($response)['props'])->toHaveKey('layout');
});

it('falls back to the original response when the error page itself cannot be built', function () {
    // Без таблицы settings layout недоступен — ответ не должен упасть второй ошибкой.
    Schema::drop('settings');

    $response = app(ErrorPageRenderer::class)->render(new Response('original', 404), new RuntimeException, Request::create('/x'));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBe('original');
});

it('routes an unmatched URL through the web group (fallback route)', function () {
    $fallback = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => $route->isFallback);

    expect($fallback)->not->toBeNull()
        ->and($fallback->gatherMiddleware())->toContain('web');

    // Web-middleware отработали до 404: SetLocale выставил язык пользователя.
    $user = User::factory()->create(['language' => 'ru']);

    test()->actingAs($user)->get('/definitely-missing')->assertNotFound();
    expect(app()->getLocale())->toBe('ru');
});

it('keeps JSON 404s for unmatched api URLs', function () {
    test()->getJson('/api/definitely-missing')->assertNotFound()->assertJsonStructure(['message']);
});

it('shares the public layout on a 405 for an unknown URL hit with another method', function () {
    $response = renderError(405, Request::create('/definitely-missing', 'POST'));

    expect($response->getStatusCode())->toBe(405)
        ->and(errorPage($response)['props'])->toHaveKey('layout');
});

it('keeps the original response for asset-like paths', function (string $path) {
    $original = new Response('original', 404);

    $response = app(ErrorPageRenderer::class)->render($original, new RuntimeException, Request::create($path));

    expect($response)->toBe($original);
})->with(['/favicon.ico', '/wp-login.php', '/build/app.js.map', '/img/Logo.PNG', '/fonts/x.woff2', '/robots.txt']);

it('answers a POST to an unknown URL with 405 from the GET-only fallback', function () {
    test()->post('/definitely-missing')->assertStatus(405);
});

it('answers an unknown asset-like URL with a plain 404 without the public layout', function () {
    $response = test()->get('/favicon.ico');

    $response->assertNotFound();
    expect($response->headers->get('X-Inertia'))->toBeNull()
        ->and(Inertia\Inertia::getShared('layout'))->toBeNull()
        ->and(Inertia\Inertia::getShared('i18n'))->toBeNull();
});
