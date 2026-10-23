<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\AppearanceSettings;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Support\TranslationMap;
use Ladcms\Core\Ui\HeaderActions;
use Ladcms\Core\Ui\Navigation;
use Ladcms\Core\Ui\Widgets;
use Symfony\Component\HttpFoundation\Response;

/**
 * Шарит в Inertia данные публичного layout (`layout`): навигация, иконки шапки, виджеты, сайт.
 * На `admin.*` — `null`. Шер выставляется всегда: ResponseFactory — синглтон, и замыкание прошлого
 * запроса в долгоживущем воркере (Octane) иначе пережило бы его и попало в админ-страницы.
 */
class SharePublicLayout
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('layout', $request->routeIs('admin.*') ? null : function (): array {
            // Запрос и пользователь — из контейнера в момент вычисления, а не из захваченного замыкания.
            $request = app(Request::class);
            $user = $request->user();
            $user = $user instanceof User ? $user : null;
            $widgets = app(Widgets::class);
            $general = app(GeneralSettings::class);
            $logo = app(AppearanceSettings::class)->logo_path;

            return [
                'navigation' => app(Navigation::class)->items($user, $request),
                'header_actions' => app(HeaderActions::class)->items($user),
                'widgets' => [
                    'sidebar' => $widgets->for('sidebar', $user),
                    'main' => $request->routeIs('home') ? $widgets->for('main', $user) : [],
                ],
                'site' => [
                    'name' => $general->site_name,
                    'url' => rtrim((string) config('app.url'), '/'),
                    'description' => $general->site_description,
                    'logo_url' => $logo !== null && $logo !== '' ? Storage::url($logo) : null,
                    'registration_open' => app(RegistrationSettings::class)->isAvailable(),
                ],
            ];
        });

        // Карта переводов — once-проп: повторно уходит на клиент только при смене локали/области/набора модулей.
        $translations = app(TranslationMap::class);
        Inertia::shareOnce('i18n', fn (): array => $translations->build($request))->as($translations->cacheKey($request));

        return $next($request);
    }
}
