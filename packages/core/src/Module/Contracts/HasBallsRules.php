<?php

namespace Ladcms\Core\Module\Contracts;

interface HasBallsRules
{
    /** @return list<array{event: class-string, label: string, amount: int}> */
    public function ballsRules(): array;
}
