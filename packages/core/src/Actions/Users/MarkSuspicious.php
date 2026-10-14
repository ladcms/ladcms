<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Ladcms\Core\Models\User;

class MarkSuspicious
{
    /** @throws AuthorizationException */
    public function handle(User $actor, User $target, bool $flag, ?string $note): User
    {
        if (! $actor->can('users.edit') || ! $actor->outranks($target)) {
            throw new AuthorizationException('You cannot change this user.');
        }

        // TODO(Task 10): Audit::log suspicious flag change.
        $target->forceFill([
            'is_suspicious' => $flag,
            'suspicious_note' => $flag ? $note : null,
        ])->save();

        return $target;
    }
}
