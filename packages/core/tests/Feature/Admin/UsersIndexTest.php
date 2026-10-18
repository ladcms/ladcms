<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    // Fortify-роуты в Testbench отсутствуют — заглушки.
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->moderator = User::factory()->create(['created_at' => now()->subYear()])->assignRole('moderator');
});

/** @return list<int> */
function usersIndexIds(AssertableInertia $page): array
{
    return collect($page->toArray()['props']['users']['data'])->pluck('id')->all();
}

function usersIndex(array $query = []): TestResponse
{
    return test()->actingAs(test()->moderator)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin/users'.($query === [] ? '' : '?'.http_build_query($query)));
}

it('redirects guests to login', function () {
    $this->get('/admin/users')->assertRedirect('/login');
});

it('forbids users without users.view', function () {
    $user = User::factory()->create()->assignRole('user');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin/users')
        ->assertForbidden();
});

it('renders the users list for moderators', function () {
    User::factory()->count(2)->create();

    usersIndex()
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/users/index')
            ->has('users.data', 3)
            ->has('users.meta.links')
            ->where('users.meta.total', 3)
            ->has('filters', fn (AssertableInertia $f) => $f
                ->where('q', '')
                ->where('role', 'all')
                ->where('status', 'all')
                ->where('sort', 'created_at')
                ->where('direction', 'desc'))
            ->has('roles', Role::query()->count())
            ->where('roles.0.name', Role::query()->orderBy('level')->value('name')));
});

it('filters banned users', function () {
    $banned = User::factory()->create();
    $expired = User::factory()->create();
    User::factory()->create();
    Ban::create(['user_id' => $banned->id, 'banned_by_id' => $this->moderator->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);
    Ban::create(['user_id' => $expired->id, 'banned_by_id' => $this->moderator->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);

    usersIndex(['status' => 'banned'])->assertInertia(function (AssertableInertia $page) use ($banned) {
        expect(usersIndexIds($page))->toBe([$banned->id]);
        $page->where('filters.status', 'banned')->where('users.data.0.is_banned', true);
    });
});

it('filters suspicious and online users', function () {
    $suspicious = User::factory()->create();
    $suspicious->forceFill(['is_suspicious' => true])->save();
    $online = User::factory()->create();
    $online->forceFill(['last_activity_at' => now()])->save();

    usersIndex(['status' => 'suspicious'])->assertInertia(
        fn (AssertableInertia $page) => expect(usersIndexIds($page))->toBe([$suspicious->id])
    );
    $offline = User::factory()->create();
    $offline->forceFill(['last_activity_at' => now()->subHour()])->save();

    // Сам модератор тоже онлайн: активность обновляется при запросе.
    usersIndex(['status' => 'online'])->assertInertia(
        fn (AssertableInertia $page) => expect(usersIndexIds($page))->toEqualCanonicalizing([$online->id, $this->moderator->id])
    );
});

it('searches by email', function () {
    $target = User::factory()->create(['email' => 'zzfinder@example.test']);
    User::factory()->create(['email' => 'other@example.test']);

    usersIndex(['q' => 'zzfind'])->assertInertia(function (AssertableInertia $page) use ($target) {
        expect(usersIndexIds($page))->toBe([$target->id]);
        $page->where('filters.q', 'zzfind');
    });
});

it('sorts by created_at', function () {
    $old = User::factory()->create(['created_at' => now()->subMonths(6)]);
    $new = User::factory()->create(['created_at' => now()->subDay()]);

    usersIndex()->assertInertia(
        fn (AssertableInertia $page) => expect(usersIndexIds($page))->toBe([$new->id, $old->id, $this->moderator->id])
    );
    usersIndex(['sort' => 'created_at', 'direction' => 'asc'])->assertInertia(function (AssertableInertia $page) use ($old, $new) {
        expect(usersIndexIds($page))->toBe([$this->moderator->id, $old->id, $new->id]);
        $page->where('filters.direction', 'asc');
    });
});

it('filters by role and ignores unknown values', function () {
    $other = User::factory()->create()->assignRole('moderator');
    User::factory()->create()->assignRole('user');

    usersIndex(['role' => 'moderator'])->assertInertia(function (AssertableInertia $page) use ($other) {
        expect(usersIndexIds($page))->toEqualCanonicalizing([$this->moderator->id, $other->id]);
        $page->where('filters.role', 'moderator');
    });
    usersIndex(['role' => 'nope', 'status' => 'weird', 'sort' => 'password'])->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users.data', 3)
        ->where('filters.role', 'all')
        ->where('filters.status', 'all')
        ->where('filters.sort', 'created_at'));
});
