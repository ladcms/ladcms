<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Auth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Support\LoginRules;

/**
 * Регистрация нового пользователя с учётом настроек регистрации. Событие `Registered`
 * диспатчит вызывающий код (Fortify), роль по умолчанию назначает слушатель AssignDefaultRole.
 */
class RegisterUser
{
    public function __construct(
        private readonly RegistrationSettings $registration,
        private readonly GeneralSettings $general,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     */
    public function handle(array $input, ?string $ip = null): User
    {
        if (! $this->registration->open) {
            throw new AuthorizationException('Registration is closed.');
        }

        if (is_string($input['login'] ?? null)) {
            $input['login'] = LoginRules::normalize($input['login']);
        }

        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        $rules = [
            'login' => LoginRules::rules(),
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ];

        if ($this->registration->require_rules) {
            $rules['rules_accepted'] = ['accepted'];
        }

        /** @var array{login: string, name: string, email: string, password: string} $data */
        $data = Validator::make($input, $rules)->validate();

        /** @var class-string<User> $model */
        $model = config('auth.providers.users.model', User::class);

        return $model::query()->create([
            'login' => $data['login'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'registered_ip' => $ip,
            'language' => $this->general->default_language,
        ]);
    }
}
