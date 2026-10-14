<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ladcms\Core\Models\LoginHistory;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\LoginRules;

class ChangeLogin
{
    public function handle(User $user, string $newLogin): User
    {
        $login = LoginRules::normalize($newLogin);

        if ($login === $user->login) {
            return $user;
        }

        Validator::make(['login' => $login], ['login' => LoginRules::rules($user->id)])->validate();

        return DB::transaction(function () use ($user, $login): User {
            LoginHistory::query()->create(['user_id' => $user->id, 'old_login' => $user->login, 'changed_at' => now()]);
            $user->forceFill(['login' => $login])->save();

            return $user;
        });
    }
}
