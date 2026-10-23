<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\EmailChange;

class UpdateUserProfile
{
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

        $newEmail = null;
        if (is_string($data['email'] ?? null)) {
            $email = mb_strtolower(trim($data['email']));
            Validator::make(['email' => $email], ['email' => [Rule::unique('users', 'email')->ignore($target->id)]])->validate();
            $newEmail = EmailChange::differs($target, $email) ? $email : null;
        }
        unset($data['email']);

        $target->fill($data);

        $changed = array_keys($target->getDirty());
        if ($newEmail !== null) {
            $changed[] = 'email';
        }

        if ($changed !== []) {
            // Смена email сохраняет модель сама (сброс подтверждения + письмо), см. EmailChange.
            if ($newEmail !== null) {
                EmailChange::apply($target, $newEmail);
            } else {
                $target->save();
            }

            Audit::log('user.updated', $actor, $target, ['changed' => $changed]);
        }

        return $target;
    }
}
