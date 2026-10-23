<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Ladcms\Core\Actions\Moderation\BanUser;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\UserBanned;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['mail.default' => 'array']);
    Mail::fake();
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->mod = User::factory()->create()->assignRole('moderator');
    $this->target = User::factory()->create()->assignRole('user');
});

it('stores a UserBanned database notification for the banned user', function () {
    $ban = app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(3), 'ads');

    $notification = DatabaseNotification::query()->sole();

    expect($notification->type)->toBe(UserBanned::class)
        ->and($notification->notifiable_id)->toBe($this->target->id)
        ->and($notification->notifiable_type)->toBe($this->target->getMorphClass())
        ->and($notification->read_at)->toBeNull()
        ->and($notification->data)->toBe([
            'ban_id' => $ban->id,
            'reason_code' => 'spam',
            'expires_at' => $ban->expires_at?->toIso8601String(),
            'title' => 'core::public.notifications.banned',
            'params' => [],
            'url' => null,
        ])
        ->and($this->mod->notifications()->count())->toBe(0);
});

it('stores a null expiry for a permanent ban', function () {
    app(BanUser::class)->handle($this->mod, $this->target, BanType::Readonly, 'rules', null);

    expect($this->target->notifications()->sole()->data['expires_at'])->toBeNull();
});

it('sends UserBanned via database and mail', function () {
    $ban = app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(3));
    $notification = new UserBanned($ban);

    expect($notification->via($this->target))->toBe(['database', 'mail'])
        ->and($notification->toMail($this->target))->toBeInstanceOf(MailMessage::class);
});

it('queues UserBanned after the surrounding transaction commits', function () {
    Notification::fake();

    app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(3));

    Notification::assertSentTo($this->target, UserBanned::class, fn (UserBanned $notification): bool => $notification->afterCommit === true);
    expect(new UserBanned(Ban::query()->sole()))->toBeInstanceOf(ShouldQueue::class);
});
