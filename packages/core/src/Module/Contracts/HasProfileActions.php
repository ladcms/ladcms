<?php

namespace Ladcms\Core\Module\Contracts;

interface HasProfileActions
{
    /** @return list<array{label: string, icon: string, component: string, order: int}> */
    public function profileActions(): array;
}
