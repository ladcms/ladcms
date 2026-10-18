<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

class EndpointTestNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return ['message' => 'hello'];
    }
}

beforeEach(function () {
    $this->me = User::factory()->create();
});

it('rejects guests', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
    $this->postJson('/api/notifications/read-all')->assertUnauthorized();
});

it('returns the latest 20 newest first with unread count', function () {
    foreach (range(1, 25) as $i) {
        $this->me->notify(new EndpointTestNotification);
    }
    $this->me->notifications()->get()->each(function ($n, $i) {
        $n->forceFill(['created_at' => now()->subMinutes(100 - $i)->startOfSecond()])->save();
    });
    $first = $this->me->notifications()->reorder()->oldest()->first();
    $first->markAsRead();

    $response = $this->actingAs($this->me)->getJson('/api/notifications')->assertOk();

    $response->assertJsonStructure([
        'unread_count',
        'notifications' => [['id', 'type', 'data', 'read_at', 'created_at']],
    ]);
    $items = $response->json('notifications');
    expect($items)->toHaveCount(20)
        ->and($response->json('unread_count'))->toBe(24)
        ->and($items[0]['type'])->toBe('EndpointTestNotification')
        ->and($items[0]['data'])->toBe(['message' => 'hello'])
        ->and($items[0]['read_at'])->toBeNull();

    $dates = array_column($items, 'created_at');
    $sorted = $dates;
    rsort($sorted);
    expect($dates)->toBe($sorted);
});

it('does not expose other users notifications', function () {
    User::factory()->create()->notify(new EndpointTestNotification);

    $this->actingAs($this->me)->getJson('/api/notifications')
        ->assertOk()->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'notifications');
});

it('marks a notification as read', function () {
    $this->me->notify(new EndpointTestNotification);
    $id = $this->me->notifications()->firstOrFail()->id;

    $this->actingAs($this->me)->postJson("/api/notifications/{$id}/read")->assertNoContent();

    expect($this->me->notifications()->firstOrFail()->read_at)->not->toBeNull();
});

it('returns 404 for another users notification', function () {
    $other = User::factory()->create();
    $other->notify(new EndpointTestNotification);
    $id = $other->notifications()->firstOrFail()->id;

    $this->actingAs($this->me)->postJson("/api/notifications/{$id}/read")->assertNotFound();

    expect($other->notifications()->firstOrFail()->read_at)->toBeNull();
});

it('returns 404 for an unknown id', function () {
    $this->actingAs($this->me)->postJson('/api/notifications/does-not-exist/read')->assertNotFound();
});

it('marks all as read', function () {
    foreach (range(1, 3) as $i) {
        $this->me->notify(new EndpointTestNotification);
    }

    $this->actingAs($this->me)->postJson('/api/notifications/read-all')->assertNoContent();

    $this->actingAs($this->me)->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
});
