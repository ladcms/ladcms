<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Install;

use RuntimeException;

final class SiteAlreadyInstalledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('LADCMS is already installed. Use --force to re-run seeders.');
    }
}
