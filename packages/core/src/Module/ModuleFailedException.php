<?php

declare(strict_types=1);

namespace Ladcms\Core\Module;

use RuntimeException;

class ModuleFailedException extends RuntimeException
{
    public function __construct(string $name, string $reason)
    {
        parent::__construct("Module [{$name}] cannot be installed: {$reason}");
    }
}
