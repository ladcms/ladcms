<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Moderation;

use Illuminate\Auth\Access\AuthorizationException;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;

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

        // Истёкший бан не трогаем: его expires_at — исторический факт.
        if ($ban->isActive()) {
            $ban->update(['expires_at' => now()]);
            Audit::log('ban.lifted', $actor, $target, ['ban_id' => $ban->id]);
        }
    }
}
