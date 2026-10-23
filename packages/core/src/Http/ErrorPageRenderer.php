<?php

declare(strict_types=1);

namespace Ladcms\Core\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Ladcms\Core\Http\Middleware\SharePublicLayout;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Рендерит публичные страницы ошибок (`core::error`) вместо дефолтных Blade-страниц Laravel.
 * Подключается в `bootstrap/app.php`: `$exceptions->respond(...)`.
 */
class ErrorPageRenderer
{
    /** Статусы, для которых показывается страница ошибки. */
    public const array STATUSES = [403, 404, 405, 500, 503];

    /** Пути, похожие на файлы/ассеты: на них не тратим сессию/layout и отдаём обычный 404 без Inertia. */
    private const string ASSET_PATH = '/\.(php|ico|map|png|jpg|jpeg|gif|svg|webp|css|js|txt|xml|json|woff2?)$/i';

    public static function isAssetPath(Request $request): bool
    {
        return preg_match(self::ASSET_PATH, $request->path()) === 1;
    }

    public function render(Response $response, Throwable $e, Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*') || self::isAssetPath($request)) {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($status === 419) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('core::public.errors.419.message')]);

            return back();
        }

        if (! in_array($status, self::STATUSES, true)) {
            return $response;
        }

        // В debug-режиме 500 — страница отладки Laravel.
        if ($status === 500 && config('app.debug')) {
            return $response;
        }

        try {
            return $this->page($request, $status)->setStatusCode($status);
        } catch (Throwable) {
            // Сама страница ошибки не должна ронять ответ (например, БД недоступна).
            return $response;
        }
    }

    private function page(Request $request, int $status): Response
    {
        $render = fn (): Response => Inertia::render('core::error', [
            'status' => $status,
            'title' => __("core::public.errors.{$status}.title"),
            'message' => __("core::public.errors.{$status}.message"),
        ])->toResponse($request);

        // 404/405 без маршрута (например, POST на несуществующий URL при GET-only fallback) приходит
        // до web-middleware: layout ещё не расшарен. Для 500/503 БД может быть недоступна — там обходимся дефолтами фронта.
        if (in_array($status, [404, 405], true) && $request->route() === null) {
            return (new SharePublicLayout)->handle($request, $render);
        }

        return $render();
    }
}
