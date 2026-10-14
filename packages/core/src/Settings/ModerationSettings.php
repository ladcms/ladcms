<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Spatie\LaravelSettings\Settings;

class ModerationSettings extends Settings
{
    /**
     * `@phpstan-var`, а не `@var`: spatie PropertyReflector режет `@var` на `{` и падает на array shape.
     *
     * @phpstan-var list<array{code: string, label: string, min_days: int, max_days: ?int}>
     */
    public array $violation_codes;

    public int $password_confirm_minutes;

    public static function group(): string
    {
        return 'moderation';
    }
}
