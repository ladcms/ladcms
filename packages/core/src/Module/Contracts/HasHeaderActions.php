<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasHeaderActions
{
    /** @return list<array{icon: string, component: string, order: int}> */
    public function headerActions(): array;
}
