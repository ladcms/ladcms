<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\DummyModule\Settings;

use Spatie\LaravelSettings\Settings;

class DummySettings extends Settings
{
    public string $greeting;

    public static function group(): string
    {
        return 'dummy';
    }
}
