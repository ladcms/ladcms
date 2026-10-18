<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Ladcms\Core\Actions\Install\CreateAdministrator;
use Ladcms\Core\Actions\Moderation\FileComplaint;
use Ladcms\Core\Actions\Users\DeleteUser;
use Ladcms\Core\Actions\Users\PruneUnverifiedUsers;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Notifications\ComplaintFiled;
use Ladcms\Core\Services\MentionService;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Support\UserModel;
use Ladcms\Core\Tests\Fixtures\AppUser\AppUser;
use Ladcms\Core\Tests\Support\AppUserAsAuthModel;
use Spatie\Activitylog\Models\Activity;

// Приложение подменяет модель пользователя наследником core User: ядро должно работать с ним так же.
uses(AppUserAsAuthModel::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

it('resolves the configured subclass as the user model', function () {
    expect(UserModel::class())->toBe(AppUser::class)
        ->and(UserModel::query()->getModel())->toBeInstanceOf(AppUser::class)
        ->and((new AppUser)->getMorphClass())->toBe('user');
});

it('creates the administrator as the app model with the creator role and its permissions', function () {
    $admin = app(CreateAdministrator::class)->handle('owner', 'Owner', 'owner@example.com', 'secret-password-123');

    expect($admin)->toBeInstanceOf(AppUser::class)
        ->and($admin->hasRole('creator'))->toBeTrue()
        ->and($admin->can('admin.access'))->toBeTrue()
        ->and($admin->hasPermissionTo('users.delete'))->toBeTrue()
        ->and(DB::table('model_has_roles')->where('model_id', $admin->id)->value('model_type'))->toBe('user');
});

it('lets a creator delete another creator when two exist', function () {
    $creator = AppUser::factory()->create()->assignRole('creator');
    $second = AppUser::factory()->create()->assignRole('creator');

    app(DeleteUser::class)->handle($creator, $second);

    expect($second->fresh()?->trashed())->toBeTrue();
});

it('notifies moderators of the app model about complaints', function () {
    Notification::fake();
    $mod = AppUser::factory()->create()->assignRole('moderator');
    $author = AppUser::factory()->create()->assignRole('user');
    $target = AppUser::factory()->create()->assignRole('user');

    app(FileComplaint::class)->handle($author, 'spam', 'ads', targetUser: $target);

    Notification::assertSentTo($mod, ComplaintFiled::class);
    Notification::assertNotSentTo($author, ComplaintFiled::class);
});

it('stores mention notifications with the user morph alias', function () {
    $author = AppUser::factory()->create();
    $mentioned = AppUser::factory()->create();

    app(MentionService::class)->notify('<span data-mention data-user-id="'.$mentioned->id.'">@x</span>', $author, $author, '/x');

    $notification = DatabaseNotification::query()->sole();

    expect($notification->notifiable_type)->toBe('user')
        ->and($notification->notifiable)->toBeInstanceOf(AppUser::class)
        ->and($mentioned->notifications()->count())->toBe(1);
});

it('records audit entries with the user morph alias resolving to the app model', function () {
    $actor = AppUser::factory()->create();
    $subject = AppUser::factory()->create();

    Audit::log('test.event', $actor, $subject);

    $activity = Activity::query()->sole();

    expect($activity->causer_type)->toBe('user')
        ->and($activity->subject_type)->toBe('user')
        ->and($activity->causer)->toBeInstanceOf(AppUser::class)
        ->and($activity->subject?->getKey())->toBe($subject->id);
});

it('keeps an unverified moderator of the app model when pruning', function () {
    $mod = AppUser::factory()->unverified()->create(['created_at' => now()->subDays(30)])->assignRole('moderator');
    $stale = AppUser::factory()->unverified()->create(['created_at' => now()->subDays(30)]);

    expect(app(PruneUnverifiedUsers::class)->handle(7))->toBe(1)
        ->and(AppUser::query()->find($mod->id))->not->toBeNull()
        ->and(AppUser::withTrashed()->find($stale->id))->toBeNull();
});
