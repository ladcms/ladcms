<?php

declare(strict_types=1);

namespace Ladcms\Core\Module;

final readonly class ModuleFailure
{
    public function __construct(
        public string $module,
        public string $reason,
    ) {}
}
