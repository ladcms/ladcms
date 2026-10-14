<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasWidgets
{
    /** @return list<array{name: string, component: string, slot: 'sidebar'|'main', order: int}> */
    public function widgets(): array;
}
