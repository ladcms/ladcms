<?php

declare(strict_types=1);

namespace Ladcms\Core\Enums;

enum MessagesFrom: string
{
    case All = 'all';
    case Friends = 'friends';
    case None = 'none';
}
