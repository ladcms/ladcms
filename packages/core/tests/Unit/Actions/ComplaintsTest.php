<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Moderation\BanUser;
use Ladcms\Core\Actions\Moderation\FileComplaint;
use Ladcms\Core\Actions\Moderation\ReviewComplaint;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\ComplaintFiled;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Notification::fake();
    $this->mod = User::factory()->create()->assignRole('moderator');
    $this->author = User::factory()->create()->assignRole('user');
    $this->target = User::factory()->create()->assignRole('user');
});

it('files a complaint against a user and notifies moderators', function () {
    $complaint = app(FileComplaint::class)->handle($this->author, 'spam', 'sends ads', targetUser: $this->target);

    expect($complaint->exists)->toBeTrue()
        ->and($complaint->user_id)->toBe($this->author->id)
        ->and($complaint->target_user_id)->toBe($this->target->id)
        ->and($complaint->reason_code)->toBe('spam')
        ->and($complaint->comment)->toBe('sends ads')
        ->and($complaint->fresh()->status)->toBe(ComplaintStatus::Pending)
        ->and($complaint->reportable_type)->toBeNull();

    Notification::assertSentTo($this->mod, ComplaintFiled::class, fn (ComplaintFiled $n, array $channels) => $channels === ['database']
        && $n->toDatabase($this->mod) === ['complaint_id' => $complaint->id, 'reason_code' => 'spam', 'author_login' => $this->author->login]);
    Notification::assertNotSentTo($this->target, ComplaintFiled::class);
    Notification::assertNotSentTo($this->author, ComplaintFiled::class);
});

it('stores the reportable morph', function () {
    $complaint = app(FileComplaint::class)->handle($this->author, 'insult', 'rude profile', $this->target);

    expect($complaint->reportable_type)->toBe($this->target->getMorphClass())
        ->and($complaint->reportable_id)->toBe($this->target->id)
        ->and($complaint->fresh()->reportable->is($this->target))->toBeTrue();
});

it('rejects a duplicate pending complaint', function () {
    app(FileComplaint::class)->handle($this->author, 'spam', 'first', $this->target);
    app(FileComplaint::class)->handle($this->author, 'spam', 'first', targetUser: $this->target);

    expect(fn () => app(FileComplaint::class)->handle($this->author, 'spam', 'again', $this->target))->toThrow(ValidationException::class)
        ->and(fn () => app(FileComplaint::class)->handle($this->author, 'spam', 'again', targetUser: $this->target))->toThrow(ValidationException::class);

    // A reviewed complaint no longer blocks a new one.
    $first = Complaint::query()->whereNotNull('reportable_id')->firstOrFail();
    app(ReviewComplaint::class)->handle($this->mod, $first, ComplaintStatus::Rejected);

    expect(app(FileComplaint::class)->handle($this->author, 'spam', 'again', $this->target)->exists)->toBeTrue();
});

it('rejects an unknown reason code', function () {
    expect(fn () => app(FileComplaint::class)->handle($this->author, 'unknown', 'x', targetUser: $this->target))->toThrow(ValidationException::class);
});

it('forbids banned authors from complaining', function () {
    app(BanUser::class)->handle($this->mod, $this->author, BanType::Full, 'spam', now()->addDays(3));

    expect(fn () => app(FileComplaint::class)->handle($this->author, 'spam', 'x', targetUser: $this->target))->toThrow(AuthorizationException::class);
});

it('lets only reviewers review complaints', function () {
    $complaint = app(FileComplaint::class)->handle($this->author, 'spam', 'x', targetUser: $this->target);

    expect(fn () => app(ReviewComplaint::class)->handle($this->target, $complaint, ComplaintStatus::Resolved))->toThrow(AuthorizationException::class);

    $reviewed = app(ReviewComplaint::class)->handle($this->mod, $complaint, ComplaintStatus::Resolved)->fresh();

    expect($reviewed->status)->toBe(ComplaintStatus::Resolved)
        ->and($reviewed->reviewed_by_id)->toBe($this->mod->id)
        ->and($reviewed->reviewed_at)->not->toBeNull();
});

it('refuses to review a complaint back to pending', function () {
    $complaint = app(FileComplaint::class)->handle($this->author, 'spam', 'x', targetUser: $this->target);

    expect(fn () => app(ReviewComplaint::class)->handle($this->mod, $complaint, ComplaintStatus::Pending))->toThrow(InvalidArgumentException::class);
});

it('refuses to review a complaint that is not pending', function () {
    $complaint = app(FileComplaint::class)->handle($this->author, 'spam', 'x', targetUser: $this->target);
    app(ReviewComplaint::class)->handle($this->mod, $complaint, ComplaintStatus::Rejected);
    $reviewer = User::factory()->create()->assignRole('moderator');

    expect(fn () => app(ReviewComplaint::class)->handle($reviewer, $complaint->fresh(), ComplaintStatus::Resolved))
        ->toThrow(InvalidArgumentException::class, 'already been reviewed');

    $fresh = $complaint->fresh();
    expect($fresh->status)->toBe(ComplaintStatus::Rejected)
        ->and($fresh->reviewed_by_id)->toBe($this->mod->id);
});

it('lets only one of two concurrent reviews win, even with a stale model', function () {
    $complaint = app(FileComplaint::class)->handle($this->author, 'spam', 'x', targetUser: $this->target);
    $staleCopy = Complaint::query()->findOrFail($complaint->id);
    $reviewer = User::factory()->create()->assignRole('moderator');

    $won = app(ReviewComplaint::class)->handle($this->mod, $complaint, ComplaintStatus::Rejected);
    expect($won->status)->toBe(ComplaintStatus::Rejected)
        ->and($won->reviewed_by_id)->toBe($this->mod->id);

    expect(fn () => app(ReviewComplaint::class)->handle($reviewer, $staleCopy, ComplaintStatus::Resolved))
        ->toThrow(InvalidArgumentException::class, 'already been reviewed');

    $fresh = $complaint->fresh();
    expect($fresh->status)->toBe(ComplaintStatus::Rejected)
        ->and($fresh->reviewed_by_id)->toBe($this->mod->id)
        ->and(Activity::query()->where('event', 'complaint.reviewed')->count())->toBe(1);
});
