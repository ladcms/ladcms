<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\UserModel;

/** Пользователь удаляет свой аккаунт (мягко). Последний создатель удалить себя не может. */
class DeleteOwnAccount
{
    /** @throws InvalidArgumentException */
    public function handle(User $user): void
    {
        if ($user->isCreator() && UserModel::query()->role('creator')->count() <= 1) {
            throw new InvalidArgumentException('The last creator cannot delete their account.');
        }

        DB::transaction(function () use ($user): void {
            $user->delete();
            Audit::log('user.deleted_own_account', $user, $user);
        });
    }
}
