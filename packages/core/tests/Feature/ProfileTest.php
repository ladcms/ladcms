<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\LoginHistory;
use Ladcms\Core\Models\User;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

function profileOf(User $user, ?User $viewer = null, string $query = ''): TestResponse
{
    $test = $viewer instanceof User ? test()->actingAs($viewer) : test();

    return $test->get('/u/'.$user->login.$query);
}

it('renders a profile for guests with the public fields', function () {
    $user = User::factory()->create(['login' => 'alice', 'name' => 'Alice', 'about' => 'Hello', 'signature' => 'Sig', 'sex' => 'female', 'language' => 'en']);

    profileOf($user)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('core::users/show')
        ->where('profile.login', 'alice')
        ->where('profile.about', 'Hello')
        ->where('profile.signature', 'Sig')
        ->where('profile.sex', 'female')
        ->where('profile.language', 'en')
        ->where('profile.is_banned', false)
        ->missing('profile.is_suspicious')
        ->where('active_tab', 'overview')
        ->where('tabs.0.key', 'overview')
        ->where('tabs.0.component', 'core::Overview')
        ->where('overview_blocks', [])
        ->where('actions', []));
});

it('hides the email when show_email is false in props and in the HTML', function () {
    $user = User::factory()->create(['email' => 'secret.person@example.test', 'show_email' => false]);

    $response = profileOf($user)->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page->missing('profile.email'));
    expect($response->getContent())->not->toContain('secret.person@example.test');
});

it('shows the email when show_email is true', function () {
    $user = User::factory()->create(['email' => 'open.person@example.test', 'show_email' => true]);

    $response = profileOf($user)->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page->where('profile.email', 'open.person@example.test'));
    expect($response->getContent())->toContain('open.person@example.test');
});

it('always shows the owner their own email', function () {
    $user = User::factory()->create(['show_email' => false]);

    profileOf($user, $user)->assertInertia(fn (AssertableInertia $page) => $page->where('profile.email', $user->email));
});

it('hides the birth date unless allowed', function () {
    $hidden = User::factory()->create(['birth_date' => '1990-05-06', 'show_birthday' => false]);
    $shown = User::factory()->create(['birth_date' => '1990-05-06', 'show_birthday' => true]);

    profileOf($hidden)->assertInertia(fn (AssertableInertia $page) => $page->missing('profile.birth_date'));
    profileOf($shown)->assertInertia(fn (AssertableInertia $page) => $page->where('profile.birth_date', '1990-05-06'));
});

it('redirects an old login to the current one with 301', function () {
    $user = User::factory()->create(['login' => 'newname']);
    LoginHistory::query()->create(['user_id' => $user->id, 'old_login' => 'oldname', 'changed_at' => now()]);

    $this->get('/u/oldname')->assertStatus(301)->assertRedirect(route('users.show', 'newname'));
    $this->get('/u/oldname?tab=dummy')->assertStatus(301)->assertRedirect(route('users.show', ['login' => 'newname', 'tab' => 'dummy']));
});

it('returns 404 for deleted and unknown users', function () {
    $user = User::factory()->create(['login' => 'gone']);
    $user->delete();

    $this->get('/u/gone')->assertNotFound();
    $this->get('/u/nobody-here')->assertNotFound();
});

it('returns 404 for the history login of a deleted user', function () {
    $user = User::factory()->create(['login' => 'current']);
    LoginHistory::query()->create(['user_id' => $user->id, 'old_login' => 'former', 'changed_at' => now()]);
    $user->delete();

    $this->get('/u/former')->assertNotFound();
});

it('exposes the public banned badge but never suspicion to plain viewers', function () {
    $user = User::factory()->create(['is_suspicious' => true]);
    Ban::create(['user_id' => $user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);
    $viewer = User::factory()->create();

    profileOf($user, $viewer)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('profile.is_banned', true)
        ->missing('profile.is_suspicious')
        ->missing('profile.ban_reason'));
});

it('shows suspicion to viewers with users.view', function () {
    $user = User::factory()->create(['is_suspicious' => true]);
    $moderator = User::factory()->create()->assignRole('moderator');

    profileOf($user, $moderator)->assertInertia(fn (AssertableInertia $page) => $page->where('profile.is_suspicious', true));
});

it('aggregates module tabs, counters, overview blocks and actions from the dummy module', function () {
    $this->app->register(DummyServiceProvider::class);
    Log::shouldReceive('warning')->atLeast()->once();
    $user = User::factory()->create();
    $viewer = User::factory()->create();

    profileOf($user, $viewer)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('tabs', 2)
        ->where('tabs.1', ['key' => 'dummy', 'label' => 'Dummy tab', 'component' => 'dummy::Tab'])
        ->where('overview_blocks', [['key' => 'dummy', 'component' => 'dummy::Block', 'order' => 10]])
        ->where('profile.counters', [['key' => 'dummy-posts', 'label' => 'Posts', 'value' => 7, 'tab' => 'dummy']])
        ->has('actions', 1)
        ->where('actions.0.component', 'dummy::Wave')
        ->where('actions.0.key', 'dummy:Wave')
        ->missing('tab_data'));
});

it('hides module actions from guests', function () {
    $this->app->register(DummyServiceProvider::class);
    Log::shouldReceive('warning')->atLeast()->once();

    profileOf(User::factory()->create())->assertInertia(fn (AssertableInertia $page) => $page->where('actions', []));
});

it('selects a module tab and falls back to overview for unknown tabs', function () {
    $this->app->register(DummyServiceProvider::class);
    Log::shouldReceive('warning')->atLeast()->once();
    $user = User::factory()->create();

    profileOf($user, null, '?tab=dummy')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('active_tab', 'dummy')
        ->where('tab_data', ['marker' => 'dummy-tab-data', 'user_id' => $user->id]));
    profileOf($user, null, '?tab=nope')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('active_tab', 'overview')
        ->missing('tab_data'));
});

it('serves tab data through a partial reload', function () {
    $this->app->register(DummyServiceProvider::class);
    Log::shouldReceive('warning')->atLeast()->once();
    $user = User::factory()->create();

    $this->get('/u/'.$user->login)->assertInertia(fn (AssertableInertia $page) => $page
        ->reloadOnly(['tab_data', 'active_tab'], fn (AssertableInertia $reloaded) => $reloaded
            ->missing('profile')
            ->where('active_tab', 'overview')
            ->etc()));

    $this->get('/u/'.$user->login.'?tab=dummy')->assertInertia(fn (AssertableInertia $page) => $page
        ->reloadOnly(['tab_data', 'active_tab'], fn (AssertableInertia $reloaded) => $reloaded
            ->where('active_tab', 'dummy')
            ->where('tab_data.marker', 'dummy-tab-data')
            ->missing('profile')));
});

it('gives guests and plain users no moderation rights', function () {
    $user = User::factory()->create();
    $expected = ['can_ban' => false, 'can_assign_roles' => false, 'can_mark_suspicious' => false, 'can_edit' => false, 'admin_url' => null];

    profileOf($user)->assertInertia(fn (AssertableInertia $page) => $page->where('moderation', $expected));
    profileOf($user, User::factory()->create())->assertInertia(fn (AssertableInertia $page) => $page->where('moderation', $expected));
});

it('lets the owner edit their own profile but nothing else', function () {
    $user = User::factory()->create();

    profileOf($user, $user)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('moderation.can_edit', true)
        ->where('moderation.can_ban', false)
        ->where('moderation.admin_url', null));
});

it('gives a moderator ban rights and the admin link', function () {
    $user = User::factory()->create();
    $moderator = User::factory()->create()->assignRole('moderator');

    profileOf($user, $moderator)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('moderation.can_ban', true)
        ->where('moderation.can_assign_roles', false)
        ->where('moderation.can_mark_suspicious', false)
        ->where('moderation.admin_url', route('admin.users.show', $user)));
});

it('gives an administrator the full set', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create()->assignRole('admin');

    profileOf($user, $admin)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('moderation.can_ban', true)
        ->where('moderation.can_assign_roles', true)
        ->where('moderation.can_mark_suspicious', true)
        ->where('moderation.can_edit', true));
});

it('hides module actions on the owner own profile', function () {
    $this->app->register(DummyServiceProvider::class);
    Log::shouldReceive('warning')->atLeast()->once();
    $user = User::factory()->create();

    profileOf($user, $user)->assertInertia(fn (AssertableInertia $page) => $page->where('actions', []));
});

it('does not evaluate heavy props on a tab-switch partial reload', function () {
    $this->app->register(DummyServiceProvider::class);
    $user = User::factory()->create();
    DummyServiceProvider::$countersCalls = 0;
    DummyServiceProvider::$sectionsCalls = 0;

    $response = $this->get('/u/'.$user->login.'?tab=dummy', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) Inertia\Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'core::users/show',
        'X-Inertia-Partial-Data' => 'tab_data,active_tab',
    ])->assertOk();

    expect($response->json('props'))->toHaveKeys(['tab_data', 'active_tab'])->not->toHaveKey('profile')
        ->and(DummyServiceProvider::$countersCalls)->toBe(0)
        ->and(DummyServiceProvider::$sectionsCalls)->toBe(1);
});

it('resolves case-insensitively without redirecting', function () {
    User::factory()->create(['login' => 'alice']);

    $this->get('/u/ALICE')->assertOk();
});

it('resolves a re-used login to its current owner without redirect', function () {
    $former = User::factory()->create(['login' => 'moved']);
    LoginHistory::query()->create(['user_id' => $former->id, 'old_login' => 'shared', 'changed_at' => now()]);
    $owner = User::factory()->create(['login' => 'shared']);

    profileOf($owner)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('profile.id', $owner->id));
});
