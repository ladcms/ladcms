<?php

declare(strict_types=1);

use Ladcms\Core\Notifications\UserMentioned;
use Ladcms\Core\Support\NotificationTypes;

it('lists the core types and lets modules register more', function () {
    expect(NotificationTypes::all())->toHaveKeys(['mentions', 'complaints', 'bans']);

    NotificationTypes::register('replies', UserMentioned::class);

    expect(NotificationTypes::all()['replies'])->toBe(UserMentioned::class)
        ->and(config('core.notification_types'))->toHaveKey('replies')
        ->toHaveKey('mentions');
});
