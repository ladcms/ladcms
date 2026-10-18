<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use InvalidArgumentException;

/**
 * Роль по умолчанию получает каждый новый пользователь: она не может быть выше уровня `user`
 * и не может давать доступ в админку — иначе регистрация раздаёт привилегии.
 */
final class DefaultRoleGuard
{
    public const string ADMIN_PERMISSION = 'admin.access';

    /**
     * @param  list<string>  $permissions
     *
     * @throws InvalidArgumentException
     */
    public static function ensureSafe(?int $level, array $permissions): void
    {
        if ($level !== null && $level > RoleLevel::USER) {
            throw new InvalidArgumentException('The default role cannot have a level above 1.');
        }

        if (in_array(self::ADMIN_PERMISSION, $permissions, true)) {
            throw new InvalidArgumentException('The default role cannot grant admin panel access.');
        }
    }
}
