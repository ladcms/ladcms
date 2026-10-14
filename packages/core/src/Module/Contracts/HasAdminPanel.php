<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasAdminPanel
{
    /** @return list<array{label: string, icon: string, route: string, order: int, children?: list<array{label: string, route: string}>}> */
    public function adminMenu(): array;
}
