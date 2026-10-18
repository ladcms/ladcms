<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ladcms\Core\Models\LoginHistory;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\LoginRules;

class ChangeLogin
{
    /**
     * Changes the login of `$user`. Authorization is the caller's job; when `$actor` is given
     * (admin-initiated change) the change is written to the audit log.
     */
    public function handle(User $user, string $newLogin, ?User $actor = null): User
    {
        $login = LoginRules::normalize($newLogin);

        if ($login === $user->login) {
            return $user;
        }

        Validator::make(['login' => $login], ['login' => LoginRules::rules($user->id)])->validate();

        return DB::transaction(function () use ($user, $login, $actor): User {
            $old = $user->login;
            LoginHistory::query()->create(['user_id' => $user->id, 'old_login' => $old, 'changed_at' => now()]);
            $user->forceFill(['login' => $login])->save();

            if ($actor instanceof User) {
                Audit::log('user.login_changed', $actor, $user, ['from' => $old, 'to' => $login]);
            }

            return $user;
        });
    }
}
