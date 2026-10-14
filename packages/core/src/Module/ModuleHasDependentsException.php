<?php

declare(strict_types=1);

namespace Ladcms\Core\Module;

use RuntimeException;

class ModuleHasDependentsException extends RuntimeException
{
    /**
     * @param  list<string>  $dependents
     */
    public function __construct(string $name, array $dependents)
    {
        parent::__construct("Module [{$name}] is required by: ".implode(', ', $dependents));
    }
}
