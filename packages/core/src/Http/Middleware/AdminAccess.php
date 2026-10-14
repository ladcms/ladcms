<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware "admin": пускает в админку только при permission `admin.access`.
 * Гость → AuthenticationException (редирект на login / 401 JSON), без права → 403.
 */
class AdminAccess
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! $user->can('admin.access')) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
