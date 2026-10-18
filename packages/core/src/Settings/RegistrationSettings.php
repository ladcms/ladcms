<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Ladcms\Core\Contracts\InviteValidator;
use Ladcms\Core\Settings\Schema\Field;
use Spatie\LaravelSettings\Settings;

class RegistrationSettings extends Settings
{
    public bool $open;

    public bool $require_email_verification;

    public bool $require_rules;

    public bool $invite_only;

    #[Field(min: 0)]
    public int $readonly_hours;

    /** Регистрация доступна: открыта и (не по инвайтам либо есть кому проверять коды). */
    public function isAvailable(): bool
    {
        return $this->open && (! $this->invite_only || app()->bound(InviteValidator::class));
    }

    public static function group(): string
    {
        return 'registration';
    }
}
