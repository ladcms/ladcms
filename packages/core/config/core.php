<?php

declare(strict_types=1);

use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Notifications\ComplaintFiled;
use Ladcms\Core\Notifications\UserBanned;
use Ladcms\Core\Notifications\UserMentioned;

return [
    'version' => CoreServiceProvider::VERSION,
    'package_json' => base_path('package.json'),
    'locales' => ['ru' => 'Русский', 'en' => 'English'],
    // Типы уведомлений, для которых пользователь выбирает каналы (ключ → класс уведомления); модули дополняют список.
    'notification_types' => [
        'mentions' => UserMentioned::class,
        'complaints' => ComplaintFiled::class,
        'bans' => UserBanned::class,
    ],
    // Типы, для которых почта включена по умолчанию (остальные — только в приложении).
    'notification_mail_default' => ['bans'],
    'prune_unverified_after_days' => 7,
    'avatars' => [
        'disk' => 'public',
        'path' => 'avatars',
        'sizes' => ['md' => 256, 'sm' => 64],
        'max_kb' => 2048,
        // Максимальная сторона исходника в px (защита от decompression bomb при декодировании).
        'max_dimension' => 4000,
    ],
];
