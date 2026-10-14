<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Spatie\LaravelSettings\Settings;

class RegistrationSettings extends Settings
{
    public bool $open;

    public bool $require_email_verification;

    public bool $require_rules;

    public bool $invite_only;

    public int $readonly_hours;

    public static function group(): string
    {
        return 'registration';
    }
}
