<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasPermissions
{
    /** @return array<string, string> permission => translation key */
    public function permissions(): array;
}
