<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasNavigation
{
    /** @return list<array{label: string, icon: string, route: string, order: int}> */
    public function navigation(): array;
}
