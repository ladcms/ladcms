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
     * Отличается ли адрес от текущего. Сравнение без учёта регистра: у старых аккаунтов (импорт из DCMS)
     * email может храниться в смешанном регистре, и пересохранение профиля не должно сбрасывать подтверждение.
     */
    public static function differs(User $user, string $email): bool
    {
        return mb_strtolower(trim($email)) !== mb_strtolower((string) $user->email);
    }

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
