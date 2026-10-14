<?php

declare(strict_types=1);

namespace Ladcms\Core\Module;

use RuntimeException;

class ModuleNotFoundException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("Module [{$name}] is not registered. Did you run `composer require ladcms/{$name}`?");
    }
}
