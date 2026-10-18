<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Settings\ModerationSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware "ladcms.password-confirm": стандартный RequirePassword, но TTL подтверждения
 * берётся из настроек модерации (`password_confirm_minutes`), а не из `auth.password_timeout`.
 * Роут `password.confirm` регистрирует Fortify; без него подтвердить пароль негде — 403.
 */
class ConfirmPasswordWithSettingsTtl
{
    public function __construct(
        private readonly RequirePassword $requirePassword,
        private readonly ModerationSettings $settings,
    ) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Route::has('password.confirm')) {
            abort(403, 'Password confirmation route is not registered.');
        }

        /** @var Response */
        return $this->requirePassword->handle(
            $request,
            $next,
            'password.confirm',
            $this->settings->password_confirm_minutes * 60,
        );
    }
}
