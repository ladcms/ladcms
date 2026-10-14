<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ladcms\Core\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Обновляет `last_activity_at` авторизованного пользователя не чаще раза в минуту.
 * Пишет напрямую через query builder: без событий модели и без изменения `updated_at`.
 */
class TrackOnline
{
    private const int THROTTLE_SECONDS = 60;

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && ($user->last_activity_at === null || $user->last_activity_at->diffInSeconds(now()) >= self::THROTTLE_SECONDS)) {
            $now = now();
            // toBase(): Eloquent-билдер сам добавил бы updated_at.
            User::query()->whereKey($user->id)->toBase()->update(['last_activity_at' => $now]);
            $user->last_activity_at = $now;
            $user->syncOriginalAttribute('last_activity_at');
        }

        return $next($request);
    }
}
