<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Install;

use Ladcms\Core\Models\User;
use Ladcms\Core\Support\AdministratorRules;
use Ladcms\Core\Support\LoginRules;
use Ladcms\Core\Support\UserModel;

final class CreateAdministrator
{
    public function handle(string $login, string $name, string $email, string $password): User
    {
        $login = LoginRules::normalize($login);

        AdministratorRules::validate($login, $name, $email, $password);

        $user = UserModel::query()->create(['login' => $login, 'name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('creator');

        return $user;
    }
}
