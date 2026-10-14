<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Временная заглушка middleware "admin"; план core заменит её на полноценную проверку permission. */
class AdminAccess
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->can('admin.access'), 403);

        return $next($request);
    }
}
