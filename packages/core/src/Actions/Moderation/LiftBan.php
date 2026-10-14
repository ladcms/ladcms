<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Moderation;

use Illuminate\Auth\Access\AuthorizationException;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

class LiftBan
{
    /** @throws AuthorizationException */
    public function handle(User $actor, Ban $ban): void
    {
        $target = $ban->user;

        if (! $actor->can('users.ban') || ($target !== null && ! $actor->outranks($target))) {
            throw new AuthorizationException('You cannot lift this ban.');
        }

        // TODO(Task 10): activity log.
        $ban->update(['expires_at' => now()]);
    }
}
