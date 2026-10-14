<?php

declare(strict_types=1);

use Ladcms\Core\CoreServiceProvider;

return [
    'version' => CoreServiceProvider::VERSION,
    'package_json' => base_path('package.json'),
    'avatars' => [
        'disk' => 'public',
        'path' => 'avatars',
        'sizes' => ['md' => 256, 'sm' => 64],
        'max_kb' => 2048,
    ],
];
