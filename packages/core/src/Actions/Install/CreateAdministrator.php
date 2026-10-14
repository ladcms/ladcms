<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Install;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\LoginRules;

final class CreateAdministrator
{
    public function handle(string $login, string $name, string $email, string $password): User
    {
        $login = LoginRules::normalize($login);

        Validator::make(
            ['login' => $login, 'name' => $name, 'email' => $email, 'password' => $password],
            [
                'login' => LoginRules::rules(),
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:12'],
            ],
        )->validate();

        $user = User::create(['login' => $login, 'name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('creator');

        return $user;
    }
}
