<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Actions\Moderation\BanUser;
use Ladcms\Core\Actions\Moderation\FileComplaint;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\UserMentioned;

uses(RefreshDatabase::class);

class PageTestNotification extends Notification
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
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->me = User::factory()->create();
});

it('rejects guests', function () {
    $this->getJson(route('notifications.page'))->assertUnauthorized();
    expect(route('notifications.page', absolute: false))->toBe('/notifications');
});

it('paginates 20 per page newest first and marks the page items read after serialising', function () {
    foreach (range(1, 25) as $i) {
        $this->me->notify(new PageTestNotification);
    }
    $this->me->notifications()->get()->each(function ($n, $i) {
        $n->forceFill(['created_at' => now()->subMinutes(100 - $i)->startOfSecond()])->save();
    });

    $this->actingAs($this->me)->get(route('notifications.page'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::notifications/index')
        ->has('notifications.data', 20)
        ->where('notifications.meta.last_page', 2)
        ->where('notifications.data.0.read_at', null)
        ->where('notifications.data.19.read_at', null)
        ->where('notifications.data', function ($items): bool {
            $dates = collect($items)->pluck('created_at')->all();
            $sorted = $dates;
            rsort($sorted);

            return $dates === $sorted;
        }));

    expect($this->me->unreadNotifications()->count())->toBe(5)
        ->and($this->me->notifications()->whereNotNull('read_at')->count())->toBe(20);

    $this->actingAs($this->me)->get(route('notifications.page', ['page' => 2]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 5));
    expect($this->me->unreadNotifications()->count())->toBe(0);
});

it('does not show or touch notifications of other users', function () {
    $other = User::factory()->create();
    $other->notify(new PageTestNotification);

    $this->actingAs($this->me)->get(route('notifications.page'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 0));

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('exposes title, params and url for every core notification type', function () {
    $mod = User::factory()->create()->assignRole('moderator');
    $author = User::factory()->create(['login' => 'alice']);
    $target = User::factory()->create();

    $mod->notify(new UserMentioned($author, $target, '/posts/1'));
    app(FileComplaint::class)->handle($author, 'spam', 'ads', targetUser: $target);
    app(BanUser::class)->handle(User::factory()->create()->assignRole('admin'), $mod, BanType::Readonly, 'rules', null);

    $items = $this->actingAs($mod)->get(route('notifications.page'))->assertOk()->inertiaProps('notifications.data');
    $byType = collect($items)->keyBy('type');

    expect($byType)->toHaveCount(3)
        ->and($byType['UserMentioned']['title'])->toBe('core::public.notifications.mentioned')
        ->and($byType['UserMentioned']['params'])->toBe(['name' => 'alice'])
        ->and($byType['UserMentioned']['url'])->toBe('/posts/1')
        ->and($byType['ComplaintFiled']['title'])->toBe('core::public.notifications.complaint')
        ->and($byType['ComplaintFiled']['url'])->toBe(route('admin.moderation.complaints.index', absolute: false))
        ->and($byType['UserBanned']['title'])->toBe('core::public.notifications.banned')
        ->and($byType['UserBanned']['url'])->toBeNull();
});

it('derives the title key from the type for legacy rows without title', function () {
    $this->me->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => UserMentioned::class,
        'data' => ['author_login' => 'bob', 'url' => '/x'],
    ]);

    $items = $this->actingAs($this->me)->get(route('notifications.page'))->inertiaProps('notifications.data');

    expect($items[0]['title'])->toBe('core::public.notifications.mentioned')
        ->and($items[0]['params'])->toBe(['name' => 'bob'])
        ->and($items[0]['url'])->toBe('/x');
});
