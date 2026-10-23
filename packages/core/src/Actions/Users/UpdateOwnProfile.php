<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\EmailChange;

/** Редактирование собственного профиля из настроек (логин, аватар, приватность — отдельные действия). */
class UpdateOwnProfile
{
    public const array FIELDS = ['name', 'email', 'sex', 'birth_date', 'about', 'signature', 'timezone'];

    /** Публичный контент: забаненный (read-only) пользователь менять его не может. */
    private const array PUBLIC_CONTENT = ['name', 'about', 'signature'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, array $data): User
    {
        $data = Arr::only($data, self::FIELDS);

        $this->authorizePublicContent($user, $data);

        Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sex' => ['sometimes', 'nullable', Rule::enum(Sex::class)],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:tomorrow'],
            'about' => ['nullable', 'string', 'max:2000'],
            'signature' => ['nullable', 'string', 'max:500'],
            'timezone' => ['sometimes', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ])->validate();

        $newEmail = null;
        if (is_string($data['email'] ?? null)) {
            $email = mb_strtolower(trim($data['email']));
            Validator::make(['email' => $email], ['email' => [Rule::unique('users', 'email')->ignore($user->id)]])->validate();
            $newEmail = EmailChange::differs($user, $email) ? $email : null;
        }
        unset($data['email']);

        $user->fill($data);

        $changed = array_keys($user->getDirty());
        if ($newEmail !== null) {
            $changed[] = 'email';
        }

        if ($changed !== []) {
            if ($newEmail !== null) {
                EmailChange::apply($user, $newEmail);
            } else {
                $user->save();
            }

            Audit::log('user.profile_updated', $user, $user, ['changed' => $changed]);
        }

        return $user;
    }

    /** @param  array<string, mixed>  $data */
    private function authorizePublicContent(User $user, array $data): void
    {
        foreach (self::PUBLIC_CONTENT as $field) {
            if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $user->{$field} && ! $user->canWrite()) {
                throw new AuthorizationException('Read-only users cannot change public profile content.');
            }
        }
    }
}
