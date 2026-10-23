<?php

declare(strict_types=1);

namespace Ladcms\Core\Notifications\Concerns;

use Ladcms\Core\Models\User;

/**
 * `via()` по настройкам пользователя: класс объявляет ключ типа в `public const TYPE`
 * (он же ключ в `core.notification_types`). Почта — только если у получателя есть email.
 */
trait RespectsPreferences
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database'];
        }

        $channels = [];

        if ($notifiable->wantsNotification(self::TYPE, 'database')) {
            $channels[] = 'database';
        }

        if ($notifiable->wantsNotification(self::TYPE, 'mail') && $notifiable->email !== '') {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
