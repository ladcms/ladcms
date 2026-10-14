<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Spatie\LaravelSettings\Settings;

class AppearanceSettings extends Settings
{
    public string $accent_color;

    public ?string $logo_path = null;

    /** system|light|dark */
    public string $default_theme;

    public static function group(): string
    {
        return 'appearance';
    }
}
