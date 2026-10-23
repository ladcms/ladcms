<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

/** Реестр типов уведомлений с выбором каналов (`config('core.notification_types')`: ключ → класс уведомления). */
final class NotificationTypes
{
    /** Модули вызывают в `boot()`: `mergeConfigFrom` поверхностный и не дополнит вложенный массив. */
    public static function register(string $key, string $class): void
    {
        config(['core.notification_types' => [...self::all(), $key => $class]]);
    }

    /** @return array<string, class-string> */
    public static function all(): array
    {
        /** @var array<string, class-string> $types */
        $types = (array) config('core.notification_types', []);

        return $types;
    }
}
