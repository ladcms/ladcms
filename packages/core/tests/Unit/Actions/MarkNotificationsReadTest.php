<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Ladcms\Core\Actions\Notifications\MarkNotificationsRead;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

class MarkReadTestNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return ['message' => 'hi'];
    }
}

it('marks only the given notifications of the user and returns the count', function () {
    $me = User::factory()->create();
    foreach (range(1, 3) as $i) {
        $me->notify(new MarkReadTestNotification);
    }
    [$a, $b, $c] = $me->notifications()->get()->all();

    $count = app(MarkNotificationsRead::class)->handle($me, [$a->id, $b->id]);

    expect($count)->toBe(2)
        ->and($a->fresh()->read_at)->not->toBeNull()
        ->and($b->fresh()->read_at)->not->toBeNull()
        ->and($c->fresh()->read_at)->toBeNull();
});

it('ignores foreign ids', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $other->notify(new MarkReadTestNotification);
    $foreign = DatabaseNotification::query()->sole();

    expect(app(MarkNotificationsRead::class)->handle($me, [$foreign->id]))->toBe(0)
        ->and($foreign->fresh()->read_at)->toBeNull();
});

it('does not count already-read notifications and handles an empty list', function () {
    $me = User::factory()->create();
    $me->notify(new MarkReadTestNotification);
    $n = $me->notifications()->sole();
    $n->markAsRead();

    expect(app(MarkNotificationsRead::class)->handle($me, [$n->id]))->toBe(0)
        ->and(app(MarkNotificationsRead::class)->handle($me, []))->toBe(0);
});
