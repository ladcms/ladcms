<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Ladcms\Core\Settings\Schema\Field;
use Ladcms\Core\Settings\Schema\FieldType;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    #[Field(FieldType::Textarea)]
    public string $site_description;

    #[Field(FieldType::Select, options: 'locales')]
    public string $default_language;

    #[Field(FieldType::Select, options: 'timezones')]
    public string $timezone;

    public string $home_page;

    #[Field(FieldType::Markdown)]
    public string $rules_markdown;

    public static function group(): string
    {
        return 'general';
    }
}
