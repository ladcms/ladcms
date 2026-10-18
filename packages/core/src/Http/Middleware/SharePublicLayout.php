<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Ladcms\Core\Contracts\InviteValidator;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\AppearanceSettings;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Ui\HeaderActions;
use Ladcms\Core\Ui\Navigation;
use Ladcms\Core\Ui\Widgets;
use Symfony\Component\HttpFoundation\Response;

/** Шарит в Inertia данные публичного layout (`layout`): навигация, иконки шапки, виджеты, сайт. На `admin.*` — ничего. */
class SharePublicLayout
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('admin.*')) {
            Inertia::share('layout', function () use ($request): array {
                $user = $request->user();
                $user = $user instanceof User ? $user : null;
                $widgets = app(Widgets::class);
                $general = app(GeneralSettings::class);
                $registration = app(RegistrationSettings::class);
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
                        'description' => $general->site_description,
                        'logo_url' => $logo !== null && $logo !== '' ? Storage::url($logo) : null,
                        'registration_open' => $registration->open
                            && (! $registration->invite_only || app()->bound(InviteValidator::class)),
                    ],
                ];
            });
        }

        return $next($request);
    }
}
