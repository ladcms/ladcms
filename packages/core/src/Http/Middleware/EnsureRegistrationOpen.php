<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ladcms\Core\Settings\RegistrationSettings;
use Symfony\Component\HttpFoundation\Response;

/** Закрывает маршруты регистрации (Fortify `register` / `register.store`), если регистрация выключена: GET — 404, остальное — 403. */
class EnsureRegistrationOpen
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('register', 'register.store') && ! app(RegistrationSettings::class)->isAvailable()) {
            abort($request->isMethod('GET') || $request->isMethod('HEAD') ? 404 : 403);
        }

        return $next($request);
    }
}
