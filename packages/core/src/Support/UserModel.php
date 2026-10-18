<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use Illuminate\Database\Eloquent\Builder;
use Ladcms\Core\Models\User;

/**
 * Модель пользователя приложения (`auth.providers.users.model`, наследник core User).
 *
 * Ядро создаёт и ищет пользователей только через неё: тогда приложение получает свои экземпляры
 * (App\Models\User с kit-трейтами), а не базовый класс ядра.
 */
final class UserModel
{
    /** Морф-алиас пользователя: одинаков для core User и любого наследника (роли, уведомления, журнал). */
    public const string MORPH_ALIAS = 'user';

    /** @return class-string<User> */
    public static function class(): string
    {
        $model = config('auth.providers.users.model');

        // Не наследник core User (например, не настроенное приложение) — откатываемся на базовую модель.
        return is_string($model) && is_a($model, User::class, true) ? $model : User::class;
    }

    /** @return Builder<User> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
