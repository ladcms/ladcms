<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Auth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Contracts\InviteValidator;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Support\LoginRules;
use Ladcms\Core\Support\UserModel;

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
     * @throws ValidationException
     */
    public function handle(array $input, ?string $ip = null): User
    {
        if (! $this->registration->isAvailable()) {
            throw new AuthorizationException($this->registration->open ? 'Registration is by invitation only.' : 'Registration is closed.');
        }

        $invites = $this->inviteValidator();

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

        if ($invites instanceof InviteValidator) {
            $rules['invite_code'] = ['required', 'string', 'max:255'];
        }

        /** @var array{login: string, name: string, email: string, password: string, invite_code?: string} $data */
        $data = Validator::make($input, $rules)->validate();

        if ($invites instanceof InviteValidator && ! $invites->validate($data['invite_code'] ?? '', $data['email'])) {
            throw ValidationException::withMessages(['invite_code' => 'The invite code is invalid.']);
        }

        return DB::transaction(function () use ($data, $ip, $invites): User {
            $user = UserModel::query()->create([
                'login' => $data['login'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'language' => $this->general->default_language,
            ]);

            // Служебные поля не mass-assignable. Подтверждение email отключено — аккаунт сразу подтверждён.
            $user->forceFill([
                'registered_ip' => $ip,
                'email_verified_at' => $this->registration->require_email_verification ? null : now(),
            ])->save();

            if ($invites instanceof InviteValidator) {
                $invites->consume($data['invite_code'] ?? '', $user);
            }

            return $user;
        });
    }

    /**
     * Валидатор инвайтов, если регистрация только по приглашениям.
     *
     * @throws AuthorizationException без биндинга InviteValidator (некому проверить код)
     */
    private function inviteValidator(): ?InviteValidator
    {
        if (! $this->registration->invite_only) {
            return null;
        }

        if (! app()->bound(InviteValidator::class)) {
            throw new AuthorizationException('Registration is by invitation only.');
        }

        return app(InviteValidator::class);
    }
}
