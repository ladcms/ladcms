<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Ladcms\Core\Models\User;

class DeleteUser
{
    /**
     * Soft-deletes the target. The last remaining creator can never be deleted.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function handle(User $actor, User $target): void
    {
        Gate::forUser($actor)->authorize('delete', $target);

        if ($target->isCreator() && User::role('creator')->count() <= 1) {
            throw new InvalidArgumentException('The last creator cannot be deleted.');
        }

        DB::transaction(function () use ($target): void {
            // TODO(Task 10): Audit::log user deletion.
            $target->delete();
        });
    }
}
