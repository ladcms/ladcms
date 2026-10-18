<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Admin\AdminMenu;
use Ladcms\Core\Admin\MenuItem;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->moderator = User::factory()->create()->assignRole('moderator');
    $this->member = User::factory()->create(['login' => 'target_user'])->assignRole('user');
});

function moderationAs(User $actor): mixed
{
    return test()->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from('/admin/moderation/complaints');
}

function modComplaint(ComplaintStatus $status = ComplaintStatus::Pending, ?User $author = null): Complaint
{
    return Complaint::query()->create([
        'user_id' => ($author ?? User::factory()->create())->id,
        'target_user_id' => test()->member->id,
        'reason_code' => 'spam',
        'comment' => 'c',
        'status' => $status,
    ]);
}

function modBan(User $user, ?DateTimeInterface $expiresAt, ?DateTimeInterface $startsAt = null): Ban
{
    return Ban::query()->create([
        'user_id' => $user->id,
        'banned_by_id' => test()->admin->id,
        'type' => BanType::Full,
        'reason_code' => 'spam',
        'starts_at' => $startsAt ?? now()->subDay(),
        'expires_at' => $expiresAt,
    ]);
}

it('defaults the complaints list to pending', function () {
    $pending = modComplaint();
    modComplaint(ComplaintStatus::Resolved);
    modComplaint(ComplaintStatus::Rejected);

    moderationAs($this->moderator)->get('/admin/moderation/complaints')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/moderation/complaints')
            ->where('filters.status', 'pending')
            ->has('complaints.data', 1)
            ->where('complaints.data.0.id', $pending->id)
            ->where('statuses', ['pending', 'reviewed', 'resolved', 'rejected', 'all'])
            ->where('can', ['review' => true]));
});

it('filters complaints by status and falls back for unknown values', function () {
    modComplaint();
    modComplaint(ComplaintStatus::Resolved);
    modComplaint(ComplaintStatus::Rejected);

    moderationAs($this->moderator)->get('/admin/moderation/complaints?status=resolved')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('complaints.data', 1)->where('complaints.data.0.status', 'resolved'));
    moderationAs($this->moderator)->get('/admin/moderation/complaints?status=all')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('complaints.data', 3));
    moderationAs($this->moderator)->get('/admin/moderation/complaints?status=bogus')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.status', 'pending')->has('complaints.data', 1));
});

it('sorts complaints and eager-loads relations without N+1', function () {
    foreach (range(1, 4) as $i) {
        modComplaint();
    }

    // Warm-up: the first request also loads the permission cache.
    moderationAs($this->moderator)->get('/admin/moderation/complaints')->assertOk();
    DB::enableQueryLog();
    DB::flushQueryLog();
    moderationAs($this->moderator)->get('/admin/moderation/complaints?sort=status&direction=asc')->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(1, 6) as $i) {
        modComplaint(author: User::factory()->create());
    }
    DB::flushQueryLog();
    moderationAs($this->moderator)->get('/admin/moderation/complaints?sort=status&direction=asc')->assertOk();

    expect(count(DB::getQueryLog()))->toBe($few);
});

it('defaults the bans list to active and treats lifted as expired', function () {
    $active = modBan($this->member, now()->addDays(2));
    $permanent = modBan($this->moderator, null);
    modBan($this->admin, now()->subHour());

    moderationAs($this->admin)->get('/admin/moderation/bans')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/moderation/bans')
            ->where('filters.status', 'active')
            ->has('bans.data', 2)
            ->where('can', ['lift' => true]));

    moderationAs($this->admin)->get('/admin/moderation/bans?status=expired')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bans.data', 1)->where('bans.data.0.is_active', false));
    moderationAs($this->admin)->get('/admin/moderation/bans?status=all')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bans.data', 3));
    moderationAs($this->admin)->get('/admin/moderation/bans?sort=expires_at&direction=asc')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('bans.data', 2));

    expect([$active->id, $permanent->id])->each->toBeInt();
});

it('eager-loads bans relations without N+1', function () {
    modBan($this->member, null);

    moderationAs($this->admin)->get('/admin/moderation/bans')->assertOk();
    DB::enableQueryLog();
    DB::flushQueryLog();
    moderationAs($this->admin)->get('/admin/moderation/bans')->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(1, 5) as $i) {
        modBan(User::factory()->create(), null);
    }
    DB::flushQueryLog();
    moderationAs($this->admin)->get('/admin/moderation/bans')->assertOk();

    expect(count(DB::getQueryLog()))->toBe($few);
});

it('reviews a pending complaint', function () {
    $complaint = modComplaint();

    moderationAs($this->moderator)->patch("/admin/moderation/complaints/{$complaint->id}", ['status' => 'resolved'])
        ->assertRedirect('/admin/moderation/complaints')
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', __('core::admin.saved'));

    $fresh = $complaint->fresh();
    expect($fresh->status)->toBe(ComplaintStatus::Resolved)
        ->and($fresh->reviewed_by_id)->toBe($this->moderator->id)
        ->and($fresh->reviewed_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'complaint.reviewed')->count())->toBe(1);
});

it('validates the review status', function (mixed $status) {
    $complaint = modComplaint();

    moderationAs($this->moderator)->patch("/admin/moderation/complaints/{$complaint->id}", ['status' => $status])
        ->assertSessionHasErrors('status');

    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Pending);
})->with(['pending', 'bogus', null]);

it('refuses to re-review a complaint with a general error', function () {
    $complaint = modComplaint(ComplaintStatus::Rejected);

    moderationAs($this->moderator)->patch("/admin/moderation/complaints/{$complaint->id}", ['status' => 'resolved'])
        ->assertRedirect('/admin/moderation/complaints')
        ->assertSessionHasErrors('general');

    $fresh = $complaint->fresh();
    expect($fresh->status)->toBe(ComplaintStatus::Rejected)
        ->and($fresh->reviewed_by_id)->toBeNull();
});

it('forbids complaint access without complaints.review', function () {
    $complaint = modComplaint();

    moderationAs($this->member)->get('/admin/moderation/complaints')->assertForbidden();
    moderationAs($this->member)->patch("/admin/moderation/complaints/{$complaint->id}", ['status' => 'resolved'])->assertForbidden();
    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Pending);
});

it('forbids bans access without users.ban, even for a complaint reviewer', function () {
    $reviewer = User::factory()->create()->assignRole('moderator');
    $reviewer->revokePermissionTo('users.ban');
    $reviewer->givePermissionTo('complaints.review');
    $role = $reviewer->roles->first();
    $role->revokePermissionTo('users.ban');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    moderationAs($reviewer->fresh())->get('/admin/moderation/bans')->assertForbidden();
    moderationAs($this->member)->get('/admin/moderation/bans')->assertForbidden();
});

it('lifts an active ban through the user route from the bans page', function () {
    $ban = modBan($this->member, null);

    moderationAs($this->moderator)->delete("/admin/users/{$this->member->id}/ban/{$ban->id}")
        ->assertSessionHasNoErrors();

    expect($ban->fresh()->isActive())->toBeFalse();
});

/** @return list<MenuItem> */
function modMenuFor(User $user): array
{
    $request = Request::create('/admin');
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

    return app(AdminMenu::class)->build($user, $request);
}

function modSection(User $user): MenuItem
{
    return collect(modMenuFor($user))->first(fn (MenuItem $i) => $i->key === 'moderation');
}

it('shows the pending badge to a reviewer on the section and the complaints item', function () {
    modComplaint();
    modComplaint();
    modComplaint(ComplaintStatus::Resolved);

    $section = modSection($this->moderator);
    $children = collect($section->children);

    expect($section->badge)->toBe(2)
        ->and($children->firstWhere('route', 'admin.moderation.complaints.index')->badge)->toBe(2)
        ->and($children->firstWhere('route', 'admin.moderation.bans.index')->badge)->toBeNull();
});

it('hides the badge when nothing is pending', function () {
    modComplaint(ComplaintStatus::Resolved);

    $section = modSection($this->moderator);

    expect($section->badge)->toBeNull()
        ->and(collect($section->children)->firstWhere('route', 'admin.moderation.complaints.index')->badge)->toBeNull();
});

it('hides the badge from a user without complaints.review', function () {
    modComplaint();
    $banner = User::factory()->create()->assignRole('moderator');
    $banner->roles->first()->revokePermissionTo('complaints.review');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $items = collect(modMenuFor($banner->fresh()));

    expect($items->first(fn (MenuItem $i) => $i->key === 'moderation'))->toBeNull();
});

it('counts pending complaints once per menu build', function () {
    modComplaint();
    $this->moderator->can('complaints.review');

    DB::enableQueryLog();
    DB::flushQueryLog();
    modMenuFor($this->moderator);
    $counts = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'count(*)') && str_contains($q['query'], 'complaints'));

    expect($counts)->toHaveCount(1);
});
