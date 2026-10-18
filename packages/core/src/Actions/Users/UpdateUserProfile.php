<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Support\Audit;

class UpdateUserProfile
{
    public function __construct(private readonly RegistrationSettings $registration) {}

    /** Поля профиля, которые можно менять этим действием (логин, роли, флаги — отдельными действиями). */
    public const array FIELDS = ['name', 'email', 'sex', 'birth_date', 'about', 'signature', 'language', 'timezone'];

    /**
     * Updates the target's profile fields. Format validation lives in the FormRequest; the action
     * only guards authorization and the email uniqueness invariant.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $target, array $data): User
    {
        Gate::forUser($actor)->authorize('update', $target);

        $data = Arr::only($data, self::FIELDS);

        if (is_string($data['email'] ?? null)) {
            $data['email'] = mb_strtolower(trim($data['email']));
            Validator::make($data, ['email' => [Rule::unique('users', 'email')->ignore($target->id)]])->validate();
        }

        $target->fill($data);

        // Новый адрес не подтверждён; если сайт не требует подтверждения — считаем его подтверждённым (как при регистрации).
        $emailChanged = $target->isDirty('email');
        if ($emailChanged) {
            $target->forceFill(['email_verified_at' => $this->registration->require_email_verification ? null : now()]);
        }

        $changed = array_values(array_diff(array_keys($target->getDirty()), ['email_verified_at', 'first_verified_at']));

        if ($changed !== []) {
            $target->save();
            Audit::log('user.updated', $actor, $target, ['changed' => $changed]);
        }

        // Письмо — только модели с контрактом MustVerifyEmail (как делает слушатель Registered в Laravel).
        if ($emailChanged && $this->registration->require_email_verification && $target instanceof MustVerifyEmail) {
            $target->sendEmailVerificationNotification();
        }

        return $target;
    }
}
