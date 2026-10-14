<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasProfileSections
{
    /** @return array{tab?: array{label: string, component: string}, overview?: array{component: string, order: int}} */
    public function profileSections(): array;
}
