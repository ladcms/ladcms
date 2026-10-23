<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Validation\ValidationException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\NotificationTypes;

class UpdateNotificationPreferences
{
    /** Тип и канал, которые нельзя отключить: пользователь всегда видит уведомление о ликвидации/ограничении аккаунта. */
    public const string LOCKED_TYPE = 'bans';

    /**
     * Сохраняет выбор каналов; типы, не попавшие в `$preferences`, остаются как были.
     *
     * @param  array<string, array{database: bool, mail: bool}>  $preferences
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $preferences): User
    {
        $allowed = array_keys(NotificationTypes::all());
        $unknown = array_diff(array_keys($preferences), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['preferences' => 'Unknown notification type: '.implode(', ', $unknown)]);
        }

        $stored = $user->notification_preferences ?? [];

        foreach ($preferences as $type => $channels) {
            $stored[$type] = [
                'database' => $type === self::LOCKED_TYPE ? true : $channels['database'],
                'mail' => $channels['mail'],
            ];
        }

        $user->forceFill(['notification_preferences' => $stored])->save();

        return $user;
    }
}
