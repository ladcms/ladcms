<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;

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
        // Инвариант проверяется до авторизации: последнего создателя нельзя удалить никому.
        if ($target->isCreator() && User::role('creator')->count() <= 1) {
            throw new InvalidArgumentException('The last creator cannot be deleted.');
        }

        Gate::forUser($actor)->authorize('delete', $target);

        DB::transaction(function () use ($actor, $target): void {
            $target->delete();
            Audit::log('user.deleted', $actor, $target);
        });
    }
}
