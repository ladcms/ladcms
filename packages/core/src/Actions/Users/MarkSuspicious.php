<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;

class MarkSuspicious
{
    /** @throws AuthorizationException */
    public function handle(User $actor, User $target, bool $flag, ?string $note): User
    {
        if (! $actor->can('users.edit') || ! $actor->outranks($target)) {
            throw new AuthorizationException('You cannot change this user.');
        }

        $target->forceFill([
            'is_suspicious' => $flag,
            'suspicious_note' => $flag ? $note : null,
        ])->save();
        Audit::log('user.suspicious', $actor, $target, ['flag' => $flag, 'note' => $target->suspicious_note]);

        return $target;
    }
}
