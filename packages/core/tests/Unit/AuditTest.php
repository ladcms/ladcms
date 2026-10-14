<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Ladcms\Core\Actions\Moderation\BanUser;
use Ladcms\Core\Actions\Moderation\FileComplaint;
use Ladcms\Core\Actions\Moderation\LiftBan;
use Ladcms\Core\Actions\Moderation\ReviewComplaint;
use Ladcms\Core\Actions\Users\AssignRoles;
use Ladcms\Core\Actions\Users\DeleteUser;
use Ladcms\Core\Actions\Users\MarkSuspicious;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->mod = User::factory()->create()->assignRole('moderator');
    $this->target = User::factory()->create()->assignRole('user');
});

function lastActivity(string $event): ?Activity
{
    return Activity::query()->where('event', $event)->latest('id')->first();
}

it('logs ban creation with causer, subject and details', function () {
    $expires = now()->addDays(3);
    app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', $expires);

    $activity = lastActivity('ban.created');

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('admin')
        ->and($activity->description)->toBe('ban.created')
        ->and($activity->causer_id)->toBe($this->mod->id)
        ->and($activity->causer_type)->toBe($this->mod->getMorphClass())
        ->and($activity->subject_id)->toBe($this->target->id)
        ->and($activity->subject_type)->toBe($this->target->getMorphClass())
        ->and($activity->properties['type'])->toBe('full')
        ->and($activity->properties['reason_code'])->toBe('spam')
        ->and($activity->properties['expires_at'])->not->toBeNull();
});

it('stores properties when logging without a subject', function () {
    Audit::log('settings.updated', $this->admin, properties: ['group' => 'general']);

    $activity = lastActivity('settings.updated');

    expect($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->subject_id)->toBeNull()
        ->and($activity->subject_type)->toBeNull()
        ->and($activity->properties->all())->toBe(['group' => 'general']);
});

it('logs ban lifting', function () {
    $ban = app(BanUser::class)->handle($this->mod, $this->target, BanType::Full, 'spam', now()->addDays(3));
    app(LiftBan::class)->handle($this->mod, $ban);

    expect(lastActivity('ban.lifted'))->causer_id->toBe($this->mod->id)
        ->subject_id->toBe($this->target->id);
});

it('logs user administration actions', function () {
    app(AssignRoles::class)->handle($this->admin, $this->target, ['vip']);
    app(MarkSuspicious::class)->handle($this->admin, $this->target, true, 'multi-account');
    app(DeleteUser::class)->handle($this->admin, $this->target);

    expect(lastActivity('roles.assigned')->properties['roles'])->toBe(['vip'])
        ->and(lastActivity('user.suspicious')->properties->all())->toBe(['flag' => true, 'note' => 'multi-account'])
        ->and(lastActivity('user.deleted'))->subject_id->toBe($this->target->id)->causer_id->toBe($this->admin->id);
});

it('logs complaint review', function () {
    Notification::fake();
    $complaint = app(FileComplaint::class)->handle($this->target, 'spam', 'ads', targetUser: $this->admin);
    app(ReviewComplaint::class)->handle($this->mod, $complaint, ComplaintStatus::Resolved);

    $activity = lastActivity('complaint.reviewed');

    expect($activity->subject_id)->toBe($complaint->id)
        ->and($activity->subject_type)->toBe($complaint->getMorphClass())
        ->and($activity->properties['status'])->toBe('resolved');
});
