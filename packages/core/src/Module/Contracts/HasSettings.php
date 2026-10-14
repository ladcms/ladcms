<?php

namespace Ladcms\Core\Module\Contracts;

use Spatie\LaravelSettings\Settings;

interface HasSettings
{
    /** @return list<class-string<Settings>> */
    public function settings(): array;
}
