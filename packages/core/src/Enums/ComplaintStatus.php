<?php

declare(strict_types=1);

namespace Ladcms\Core\Enums;

enum ComplaintStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}
