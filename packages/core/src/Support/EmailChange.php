<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\RegistrationSettings;

/** Правило смены email — единое для админского и собственного редактирования профиля. */
final class EmailChange
{
    /**
     * Меняет адрес и сохраняет модель (вместе с уже заполненными полями). Новый адрес не подтверждён;
     * если сайт не требует подтверждения — считается подтверждённым (как при регистрации). Письмо
     * подтверждения уходит только моделям с контрактом MustVerifyEmail.
     */
    public static function apply(User $user, string $newEmail): void
    {
        $requireVerification = app(RegistrationSettings::class)->require_email_verification;

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => $requireVerification ? null : now(),
        ])->save();

        if ($requireVerification && $user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }
    }
}
