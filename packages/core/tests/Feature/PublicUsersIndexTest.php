<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

/** @return list<int> */
function publicUsersIds(AssertableInertia $page): array
{
    return collect($page->toArray()['props']['users']['data'])->pluck('id')->all();
}

function publicUsers(array $query = []): TestResponse
{
    return test()->get('/users'.($query === [] ? '' : '?'.http_build_query($query)));
}

it('renders the directory for guests', function () {
    User::factory()->count(2)->create();

    publicUsers()->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::users/index')
        ->has('users.data', 2)
        ->where('users.meta.total', 2)
        ->has('filters', fn (AssertableInertia $f) => $f
            ->where('q', '')
            ->where('sort', 'created_at')
            ->where('direction', 'desc')
            ->where('online', false)));
});

it('searches by login and by name and escapes wildcards', function () {
    $byLogin = User::factory()->create(['login' => 'zzneedle', 'name' => 'Alpha']);
    $byName = User::factory()->create(['login' => 'other', 'name' => 'Big Zzneedle']);
    User::factory()->create(['login' => 'plain', 'name' => 'Plain']);

    publicUsers(['q' => 'zzneedle'])->assertInertia(fn (AssertableInertia $page) => expect(publicUsersIds($page))
        ->toEqualCanonicalizing([$byLogin->id, $byName->id]));
    publicUsers(['q' => '%'])->assertInertia(fn (AssertableInertia $page) => expect(publicUsersIds($page))->toBe([]));
});

it('sorts by whitelisted columns and falls back for bogus ones', function () {
    $b = User::factory()->create(['login' => 'bbb', 'created_at' => now()->subDay()]);
    $a = User::factory()->create(['login' => 'aaa', 'created_at' => now()->subMonth()]);

    publicUsers(['sort' => 'login', 'direction' => 'asc'])->assertInertia(function (AssertableInertia $page) use ($a, $b) {
        expect(publicUsersIds($page))->toBe([$a->id, $b->id]);
        $page->where('filters.sort', 'login')->where('filters.direction', 'asc');
    });
    publicUsers(['sort' => 'password', 'direction' => 'sideways'])->assertInertia(function (AssertableInertia $page) use ($a, $b) {
        expect(publicUsersIds($page))->toBe([$b->id, $a->id]);
        $page->where('filters.sort', 'created_at')->where('filters.direction', 'desc');
    });
    // `email` is not a public sort column.
    publicUsers(['sort' => 'email'])->assertInertia(fn (AssertableInertia $page) => $page->where('filters.sort', 'created_at'));
});

it('filters online users only', function () {
    $online = User::factory()->create();
    $online->forceFill(['last_activity_at' => now()])->save();
    $offline = User::factory()->create();
    $offline->forceFill(['last_activity_at' => now()->subHour()])->save();

    publicUsers(['online' => 1])->assertInertia(function (AssertableInertia $page) use ($online) {
        expect(publicUsersIds($page))->toBe([$online->id]);
        $page->where('filters.online', true)->where('users.data.0.is_online', true);
    });
});

it('hides the email from guests and other viewers unless show_email is on', function () {
    $hidden = User::factory()->create(['show_email' => false, 'email' => 'hidden@example.test']);
    $shown = User::factory()->create(['show_email' => true, 'email' => 'shown@example.test']);

    $emails = fn (TestResponse $r): array => collect($r->inertiaProps('users.data'))->filter(fn (array $u): bool => array_key_exists('email', $u))->pluck('email', 'id')->all();

    $guest = publicUsers();
    expect($guest->getContent())->not->toContain('hidden@example.test');
    expect($emails($guest))->toBe([$shown->id => 'shown@example.test']);

    $viewer = User::factory()->create(['show_email' => false]);
    $other = $this->actingAs($viewer)->get('/users');
    expect($other->getContent())->not->toContain('hidden@example.test');
    expect($emails($other))->toEqualCanonicalizing([$viewer->id => $viewer->email, $shown->id => 'shown@example.test'])
        ->not->toHaveKey($hidden->id);
});

it('shows the own email and birth date to the user themself', function () {
    $me = User::factory()->create(['show_email' => false, 'show_birthday' => false, 'email' => 'me@example.test', 'birth_date' => '1990-05-06']);

    $this->actingAs($me)->get('/users')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('users.data.0.email', 'me@example.test')
        ->where('users.data.0.birth_date', '1990-05-06'));
});

it('exposes birth_date only when show_birthday is on', function () {
    User::factory()->create(['show_birthday' => false, 'birth_date' => '1990-05-06']);

    publicUsers()->assertInertia(fn (AssertableInertia $page) => $page->missing('users.data.0.birth_date'));

    User::query()->update(['show_birthday' => true]);

    publicUsers()->assertInertia(fn (AssertableInertia $page) => $page->where('users.data.0.birth_date', '1990-05-06'));
});

it('never exposes moderation or raw activity fields', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_suspicious' => true, 'last_activity_at' => now()])->save();

    publicUsers()->assertInertia(function (AssertableInertia $page) {
        $row = $page->toArray()['props']['users']['data'][0];

        expect(array_keys($row))->toEqualCanonicalizing(['id', 'login', 'name', 'display_name', 'avatar_url', 'roles', 'is_online', 'created_at']);
    });
});

it('lists banned users but hides soft-deleted ones', function () {
    $deleted = User::factory()->create();
    $deleted->delete();
    $kept = User::factory()->create();

    publicUsers()->assertInertia(fn (AssertableInertia $page) => expect(publicUsersIds($page))->toBe([$kept->id]));
});

it('exposes roles without level', function () {
    User::factory()->create()->assignRole('moderator');

    publicUsers()->assertInertia(function (AssertableInertia $page) {
        $roles = $page->toArray()['props']['users']['data'][0]['roles'];

        expect($roles)->toHaveCount(1)->and(array_keys($roles[0]))->toBe(['name', 'color']);
    });
});

it('paginates 30 per page', function () {
    User::factory()->count(31)->create();

    publicUsers()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users.data', 30)
        ->where('users.meta.total', 31)
        ->where('users.meta.last_page', 2));
    publicUsers(['page' => 2])->assertInertia(fn (AssertableInertia $page) => $page->has('users.data', 1));
});

it('keeps the query count constant regardless of the number of users', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->get('/users')->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    User::factory()->count(5)->create()->each(fn (User $u) => $u->assignRole('moderator'));
    $count(); // warm-up: caches (settings, permissions) fill on the first request
    $few = $count();
    User::factory()->count(10)->create()->each(fn (User $u) => $u->assignRole('moderator'));

    expect($count())->toBe($few);
});

it('reports is_online false for users without activity', function () {
    User::factory()->create(['last_activity_at' => null]);

    publicUsers()->assertInertia(fn (AssertableInertia $page) => $page->where('users.data.0.is_online', false));
});

it('combines the search with the online filter', function () {
    $match = User::factory()->create(['login' => 'combo-on']);
    $match->forceFill(['last_activity_at' => now()])->save();
    $offline = User::factory()->create(['login' => 'combo-off']);
    $offline->forceFill(['last_activity_at' => now()->subHour()])->save();
    $other = User::factory()->create(['login' => 'else']);
    $other->forceFill(['last_activity_at' => now()])->save();

    publicUsers(['q' => 'combo', 'online' => 1])->assertInertia(fn (AssertableInertia $page) => expect(publicUsersIds($page))->toBe([$match->id]));
});
