<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasProfileActions
{
    /** @return list<array{label: string, icon: string, component: string, order: int}> */
    public function profileActions(): array;
}
