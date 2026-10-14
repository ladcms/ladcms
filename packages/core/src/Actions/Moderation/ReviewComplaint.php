<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Moderation;

use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;

class ReviewComplaint
{
    /**
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function handle(User $reviewer, Complaint $complaint, ComplaintStatus $status): Complaint
    {
        if (! $reviewer->can('complaints.review')) {
            throw new AuthorizationException('You cannot review complaints.');
        }

        if ($status === ComplaintStatus::Pending) {
            throw new InvalidArgumentException('A complaint cannot be reviewed back to pending.');
        }

        $complaint->update([
            'status' => $status,
            'reviewed_by_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
        Audit::log('complaint.reviewed', $reviewer, $complaint, ['status' => $status->value]);

        return $complaint;
    }
}
