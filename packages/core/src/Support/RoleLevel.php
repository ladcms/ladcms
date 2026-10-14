<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

final class RoleLevel
{
    public const int GUEST = 0;

    public const int USER = 1;

    public const int MODERATOR = 2;

    public const int SUPER_MODERATOR = 3;

    public const int ADMIN = 4;

    public const int MAIN_ADMIN = 5;

    public const int CREATOR = 6;

    /** @return array<int, string> level => role name */
    public static function slugs(): array
    {
        return [
            self::GUEST => 'guest',
            self::USER => 'user',
            self::MODERATOR => 'moderator',
            self::SUPER_MODERATOR => 'super_moderator',
            self::ADMIN => 'admin',
            self::MAIN_ADMIN => 'main_admin',
            self::CREATOR => 'creator',
        ];
    }
}
