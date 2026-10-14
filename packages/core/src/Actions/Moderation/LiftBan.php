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
        // Цель может быть мягко удалена — ранг проверяем и для неё; без цели — отказ (fail closed).
        $target = $ban->user()->withTrashed()->first();

        if (! $target instanceof User || ! $actor->can('users.ban') || ! $actor->outranks($target)) {
            throw new AuthorizationException('You cannot lift this ban.');
        }

        // TODO(Task 10): activity log.
        if ($ban->isActive()) {
            $ban->update(['expires_at' => now()]);
        }
    }
}
