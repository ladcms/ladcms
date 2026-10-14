<?php

declare(strict_types=1);

namespace Ladcms\Core\Enums;

enum BanType: string
{
    case Full = 'full';
    case Readonly = 'readonly';
}
