<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Admin\DashboardStats;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Ladcms\Core\Tests\Fixtures\BrokenModule\BrokenServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->admin = User::factory()->create()->assignRole('admin');
});

function dashboardAs(User $actor): mixed
{
    return test()->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()]);
}

it('collects stats from the data', function () {
    // Фиксированный полдень: `users_today` и «час назад» не должны пересекать полночь.
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->admin->forceFill(['created_at' => now()])->save();
    $online = User::factory()->create(['last_activity_at' => now()->subMinutes(5)]);
    User::factory()->create(['last_activity_at' => now()->subHour()]);
    User::factory()->create(['created_at' => now()->subDays(3)]);
    $target = User::factory()->create();

    Complaint::query()->create(['user_id' => $online->id, 'target_user_id' => $target->id, 'reason_code' => 'spam', 'comment' => 'c', 'status' => ComplaintStatus::Pending]);
    Complaint::query()->create(['user_id' => $online->id, 'target_user_id' => $target->id, 'reason_code' => 'spam', 'comment' => 'c', 'status' => ComplaintStatus::Rejected]);
    Ban::query()->create(['user_id' => $target->id, 'banned_by_id' => $this->admin->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subDay(), 'expires_at' => null]);
    Ban::query()->create(['user_id' => $online->id, 'banned_by_id' => $this->admin->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subDays(3), 'expires_at' => now()->subDay()]);
    Module::create(['name' => 'core', 'version' => '0.1.0', 'installed_at' => now(), 'is_enabled' => true]);

    $stats = app(DashboardStats::class)->collect();

    expect($stats['users_total'])->toBe(5)
        ->and($stats['users_online'])->toBe(1)
        ->and($stats['users_today'])->toBe(4)
        ->and($stats['pending_complaints'])->toBe(1)
        ->and($stats['active_bans'])->toBe(1)
        ->and($stats['modules'])->toBe(['installed' => 1, 'failed' => 0, 'disabled' => 0]);
});

it('renders the dashboard with stats and recent lists', function () {
    User::factory()->count(6)->create();
    foreach (range(1, 12) as $i) {
        Audit::log('test.event', $this->admin, null, ['i' => $i]);
    }

    dashboardAs($this->admin)->get('/admin')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/dashboard')
            ->where('stats.users_total', 7)
            ->has('stats.modules')
            ->has('recent_activity', 10)
            ->where('recent_activity.0.properties.i', 12)
            ->has('recent_users', 5)
            ->has('recent_users.0', fn (AssertableInertia $u) => $u->hasAll(['id', 'login', 'created_at'])));
});

it('lets a user with admin.access only see the dashboard', function () {
    $moderator = User::factory()->create()->assignRole('moderator');

    dashboardAs($moderator)->get('/admin')->assertOk();
});

it('hides recent activity and recent users without activity.view and users.view', function () {
    Audit::log('test.event', $this->admin, null, []);
    $role = Role::create(['name' => 'panel-only', 'guard_name' => 'web']);
    $role->givePermissionTo('admin.access');
    $viewer = User::factory()->create()->assignRole($role);

    dashboardAs($viewer)->get('/admin')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('stats')
            ->where('recent_activity', null)
            ->where('recent_users', null));

    $role->givePermissionTo(['activity.view', 'users.view']);
    dashboardAs($viewer->fresh())->get('/admin')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('recent_activity', 1)
            ->has('recent_users'));
});

it('counts admin-disabled modules separately from failed ones', function () {
    $this->app->register(BrokenServiceProvider::class);
    Module::create(['name' => 'dummy', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => false]);
    $this->app->register(DummyServiceProvider::class);

    expect(app(DashboardStats::class)->collect()['modules'])->toMatchArray(['failed' => 1, 'disabled' => 1]);
});

it('survives activity whose subject class no longer exists', function () {
    Activity::query()->create(['log_name' => 'admin', 'event' => 'gone.thing', 'description' => 'x', 'subject_type' => 'Vendor\\Gone\\Model', 'subject_id' => 5]);

    dashboardAs($this->admin)->get('/admin')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('recent_activity.0.subject.type', 'Model'));
});
