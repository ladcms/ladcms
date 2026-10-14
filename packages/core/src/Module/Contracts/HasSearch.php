<?php

namespace Ladcms\Core\Module\Contracts;

interface HasSearch
{
    /** @return list<class-string> */
    public function searchables(): array;
}
