<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Auth;

use Illuminate\Support\Facades\Hash;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\LoginRules;

/** Находит пользователя по логину или email (если есть `@`) и проверяет пароль. Удалённых не пускает. */
class AuthenticateByLoginOrEmail
{
    public function handle(string $identifier, string $password): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return null;
        }

        /** @var class-string<User> $model */
        $model = config('auth.providers.users.model', User::class);

        $user = str_contains($identifier, '@')
            ? $model::query()->where('email', mb_strtolower($identifier))->first()
            : $model::query()->where('login', LoginRules::normalize($identifier))->first();

        if (! $user instanceof User || ! Hash::check($password, $user->getAuthPassword())) {
            return null;
        }

        return $user;
    }
}
