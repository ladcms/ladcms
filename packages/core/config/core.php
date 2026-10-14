<?php

declare(strict_types=1);

use Ladcms\Core\CoreServiceProvider;

return [
    'version' => CoreServiceProvider::VERSION,
    'package_json' => base_path('package.json'),
    'locales' => ['ru' => 'Русский', 'en' => 'English'],
    'prune_unverified_after_days' => 7,
    'avatars' => [
        'disk' => 'public',
        'path' => 'avatars',
        'sizes' => ['md' => 256, 'sm' => 64],
        'max_kb' => 2048,
    ],
];
