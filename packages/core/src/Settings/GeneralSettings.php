<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $site_description;

    public string $default_language;

    public string $timezone;

    public string $home_page;

    public string $rules_markdown;

    public static function group(): string
    {
        return 'general';
    }
}
