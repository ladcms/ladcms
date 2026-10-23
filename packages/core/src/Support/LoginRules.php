<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use Illuminate\Validation\Rule;
use Ladcms\Core\Rules\LoginNotRecentlyReleased;

final class LoginRules
{
    public const int MAX_LENGTH = 32;

    public const string REGEX = '/^[a-z0-9_]+$/';

    public static function normalize(string $login): string
    {
        return mb_strtolower(trim($login));
    }

    /** @return array<int, mixed> */
    public static function rules(?int $ignoreUserId = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:'.self::MAX_LENGTH, 'regex:'.self::REGEX,
            Rule::unique('users', 'login')->ignore($ignoreUserId),
            new LoginNotRecentlyReleased($ignoreUserId),
        ];
    }
}
