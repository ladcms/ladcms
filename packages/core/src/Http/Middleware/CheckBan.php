<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\User;
use Symfony\Component\HttpFoundation\Response;

/** Отправляет пользователей с активным полным баном на `/banned` (JSON — 403). */
class CheckBan
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $request->routeIs('banned', 'logout') && $user->activeBan()?->type === BanType::Full) {
            if ($request->expectsJson()) {
                abort(403, 'Your account is banned.');
            }

            return redirect()->route('banned');
        }

        return $next($request);
    }
}
