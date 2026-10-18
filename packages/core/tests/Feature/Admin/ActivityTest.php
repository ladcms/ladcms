<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->admin = User::factory()->create(['login' => 'boss_admin'])->assignRole('admin');
    $this->other = User::factory()->create(['login' => 'other_guy'])->assignRole('admin');
});

function activityAs(User $actor): mixed
{
    return test()->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()]);
}

it('lists activity with eager-loaded causer and subject and the events list', function () {
    Audit::log('role.created', $this->admin, $this->other, ['a' => 1]);
    Audit::log('role.deleted', $this->other);

    activityAs($this->admin)->get('/admin/activity')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/activity/index')
            ->has('activity.data', 2)
            ->has('activity.meta')
            ->where('events', ['role.created', 'role.deleted'])
            ->has('filters'));
});

it('filters by event', function () {
    Audit::log('role.created', $this->admin);
    Audit::log('role.deleted', $this->admin);

    activityAs($this->admin)->get('/admin/activity?event=role.deleted')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activity.data', 1)
            ->where('activity.data.0.event', 'role.deleted')
            ->where('filters.event', 'role.deleted'));
});

it('filters by causer login substring', function () {
    Audit::log('a.one', $this->admin);
    Audit::log('a.two', $this->other);

    activityAs($this->admin)->get('/admin/activity?causer=other')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activity.data', 1)
            ->where('activity.data.0.event', 'a.two'));
});

it('filters by inclusive date range', function () {
    Audit::log('old', $this->admin);
    Audit::log('mid', $this->admin);
    Audit::log('new', $this->admin);
    Activity::query()->where('event', 'old')->update(['created_at' => '2026-01-01 08:00:00']);
    Activity::query()->where('event', 'mid')->update(['created_at' => '2026-02-10 23:59:59']);
    Activity::query()->where('event', 'new')->update(['created_at' => '2026-03-01 00:00:00']);

    activityAs($this->admin)->get('/admin/activity?from=2026-02-10&to=2026-02-10')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activity.data', 1)
            ->where('activity.data.0.event', 'mid'));

    activityAs($this->admin)->get('/admin/activity?from=2026-02-01')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('activity.data', 2));
});

it('rejects an invalid date', function () {
    activityAs($this->admin)->from('/admin/activity')->get('/admin/activity?from=nonsense')
        ->assertSessionHasErrors('from');
});

it('is forbidden without activity.view', function () {
    $moderator = User::factory()->create()->assignRole('moderator');

    activityAs($moderator)->get('/admin/activity')->assertForbidden();
});

it('survives activity whose subject class no longer exists', function () {
    Activity::query()->create(['log_name' => 'admin', 'event' => 'gone.thing', 'description' => 'x', 'subject_type' => 'Vendor\\Gone\\Model', 'subject_id' => 5]);
    Audit::log('role.created', $this->admin, $this->other);

    activityAs($this->admin)->get('/admin/activity')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('activity.data', 2));
});
