<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Правила данных администратора: общие для InstallSite (проверка до сидеров) и CreateAdministrator. */
final class AdministratorRules
{
    /**
     * @param  string  $login  уже нормализованный логин (LoginRules::normalize)
     *
     * @throws ValidationException
     */
    public static function validate(string $login, string $name, string $email, string $password): void
    {
        Validator::make(
            ['login' => $login, 'name' => $name, 'email' => $email, 'password' => $password],
            [
                'login' => LoginRules::rules(),
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:12'],
            ],
        )->validate();
    }
}
