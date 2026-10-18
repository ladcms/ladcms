<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\LoginRules;
use Ladcms\Core\Support\UserModel;

/**
 * Находит пользователя по логину или email (если есть `@`) и проверяет пароль. Удалённых не пускает.
 *
 * Как и стандартный путь Fortify: вся проверка в Timebox (200 мс), а при промахе выполняется
 * фиктивная проверка хеша — время ответа не выдаёт существование логина/email.
 * При успехе пароль перехешируется, если изменились параметры хеширования (`hashing.rehash_on_login`).
 */
class AuthenticateByLoginOrEmail
{
    /** Бюджет Timebox в микросекундах — как у Fortify. */
    public const int TIMEBOX_MICROSECONDS = 200_000;

    /** Хеш-заглушка для промахов; считается лениво текущим драйвером, чтобы стоимость совпадала с реальной. */
    private static ?string $dummyHash = null;

    public function handle(string $identifier, string $password): ?User
    {
        return (new Timebox)->call(fn (): ?User => $this->attempt(trim($identifier), $password), self::TIMEBOX_MICROSECONDS);
    }

    private function attempt(string $identifier, string $password): ?User
    {
        $user = $identifier === '' ? null : $this->find($identifier);

        if (! $user instanceof User) {
            Hash::check($password, self::$dummyHash ??= Hash::make('ladcms-dummy-password'));

            return null;
        }

        if ($password === '' || ! Hash::check($password, $user->getAuthPassword())) {
            return null;
        }

        if (config('hashing.rehash_on_login', true)) {
            Auth::guard()->getProvider()->rehashPasswordIfRequired($user, ['password' => $password]);
        }

        return $user;
    }

    private function find(string $identifier): ?User
    {
        return str_contains($identifier, '@')
            ? UserModel::query()->where('email', mb_strtolower($identifier))->first()
            : UserModel::query()->where('login', LoginRules::normalize($identifier))->first();
    }
}
