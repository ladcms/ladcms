<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Auth\Access\AuthorizationException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\LoginRules;

/** Смена собственного логина из настроек: логин — самое публичное поле, read-only пользователь менять его не может. */
class ChangeOwnLogin
{
    public function __construct(private readonly ChangeLogin $changeLogin) {}

    /** @throws AuthorizationException */
    public function handle(User $user, string $newLogin): User
    {
        if (LoginRules::normalize($newLogin) !== $user->login && ! $user->canWrite()) {
            throw new AuthorizationException('Read-only users cannot change their login.');
        }

        return $this->changeLogin->handle($user, $newLogin);
    }
}
