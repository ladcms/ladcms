<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Ladcms\Core\Settings\Schema\Field;
use Ladcms\Core\Settings\Schema\FieldType;
use Spatie\LaravelSettings\Settings;

class AppearanceSettings extends Settings
{
    #[Field(FieldType::Color)]
    public string $accent_color;

    public ?string $logo_path = null;

    /** system|light|dark */
    #[Field(FieldType::Select, options: 'themes')]
    public string $default_theme;

    public static function group(): string
    {
        return 'appearance';
    }
}
