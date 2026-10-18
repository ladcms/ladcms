<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Ladcms\Core\Settings\Schema\Field;
use Ladcms\Core\Settings\Schema\FieldType;
use Spatie\LaravelSettings\Settings;

class ModerationSettings extends Settings
{
    /**
     * `@phpstan-var`, а не `@var`: spatie PropertyReflector режет `@var` на `{` и падает на array shape.
     *
     * Пустой список сделал бы бан невозможным — хотя бы один код обязателен (`min: 1`).
     *
     * @phpstan-var list<array{code: string, label: string, min_days: int, max_days: ?int}>
     */
    #[Field(FieldType::ViolationCodes, min: 1)]
    public array $violation_codes;

    /** 0 означал бы бесконечный цикл подтверждения пароля. */
    #[Field(min: 1, max: 1440)]
    public int $password_confirm_minutes;

    public static function group(): string
    {
        return 'moderation';
    }
}
