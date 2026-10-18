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
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    // Fortify-роуты в Testbench отсутствуют — заглушки.
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->moderator = User::factory()->create()->assignRole('moderator');
    $this->member = User::factory()->create(['login' => 'target_user'])->assignRole('user');
});

/** Запрос от имени актора с подтверждённым паролем; `from` — для `back()`. */
function userActionsAs(User $actor): mixed
{
    return test()->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from('/admin/users/'.test()->member->id);
}

function assertUserActionSaved(TestResponse $response): TestResponse
{
    return $response->assertRedirect('/admin/users/'.test()->member->id)
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', __('core::admin.saved'));
}

it('renders the user card with full abilities for an admin', function () {
    Ban::create(['user_id' => $this->member->id, 'banned_by_id' => $this->admin->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subDays(3), 'expires_at' => now()->subDay()]);

    userActionsAs($this->admin)->get("/admin/users/{$this->member->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/users/show')
            ->where('user.id', $this->member->id)
            ->where('user.login', 'target_user')
            ->where('user.active_ban', null)
            ->where('user.bans_count', 1)
            ->has('roles', Role::query()->count())
            ->where('assignable_roles', fn ($roles) => collect($roles)->sort()->values()->all() === ['guest', 'moderator', 'super_moderator', 'user', 'vip'])
            ->has('violation_codes', 3)
            ->where('violation_codes.0.code', 'spam')
            ->has('bans', 1)
            ->where('bans.0.reason_code', 'spam')
            ->where('can', [
                'update' => true,
                'assignRoles' => true,
                'ban' => true,
                'delete' => true,
                'markSuspicious' => true,
            ]));
});

it('limits abilities for a moderator', function () {
    userActionsAs($this->moderator)->get("/admin/users/{$this->member->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can', [
                'update' => false,
                'assignRoles' => false,
                'ban' => true,
                'delete' => false,
                'markSuspicious' => false,
            ]));
});

it('lists every role as assignable for the creator', function () {
    $creator = User::factory()->create()->assignRole('creator');

    userActionsAs($creator)->get("/admin/users/{$this->member->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('assignable_roles', Role::query()->orderBy('level')->orderBy('name')->pluck('name')->all()));
});

it('forbids the card without users.view and 404s for deleted users', function () {
    $plain = User::factory()->create()->assignRole('user');
    userActionsAs($plain)->get("/admin/users/{$this->member->id}")->assertForbidden();

    $this->member->delete();
    userActionsAs($this->admin)->get("/admin/users/{$this->member->id}")->assertNotFound();
});

// Review Focus 1: без users.ban — can.ban=false и POST ban → 403.
it('hides and forbids banning for staff without users.ban', function () {
    Role::findByName('moderator', 'web')->revokePermissionTo('users.ban');
    $viewer = $this->moderator->fresh();

    userActionsAs($viewer)->get("/admin/users/{$this->member->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.ban', false));

    userActionsAs($viewer)->post("/admin/users/{$this->member->id}/ban", [
        'type' => 'full', 'reason_code' => 'spam', 'expires_in_days' => 3,
    ])->assertForbidden();

    expect($this->member->bans()->count())->toBe(0);
});

// Review Focus 2: модератор не может назначить admin — 403, роли не меняются.
it('forbids a moderator from assigning roles', function () {
    userActionsAs($this->moderator)->put("/admin/users/{$this->member->id}/roles", ['roles' => ['admin']])
        ->assertForbidden();

    expect($this->member->fresh()->getRoleNames()->all())->toBe(['user']);
});

it('forbids an admin from assigning roles at their own level', function () {
    userActionsAs($this->admin)->put("/admin/users/{$this->member->id}/roles", ['roles' => ['admin']])
        ->assertForbidden();

    expect($this->member->fresh()->getRoleNames()->all())->toBe(['user']);
});

it('lets an admin assign a lower role', function () {
    assertUserActionSaved(userActionsAs($this->admin)->put("/admin/users/{$this->member->id}/roles", ['roles' => ['moderator']]));

    expect($this->member->fresh()->getRoleNames()->all())->toBe(['moderator']);
});

it('validates role names', function () {
    userActionsAs($this->admin)->put("/admin/users/{$this->member->id}/roles", ['roles' => ['nope']])
        ->assertSessionHasErrors('roles.0');
});

it('bans a user with a valid code', function () {
    assertUserActionSaved(userActionsAs($this->moderator)->post("/admin/users/{$this->member->id}/ban", [
        'type' => 'readonly', 'reason_code' => 'spam', 'expires_in_days' => 3, 'comment' => 'flood',
    ]));

    $ban = $this->member->activeBan();
    expect($ban)->not->toBeNull()
        ->and($ban->type)->toBe(BanType::Readonly)
        ->and($ban->comment)->toBe('flood')
        ->and($ban->banned_by_id)->toBe($this->moderator->id)
        ->and((int) round(now()->diffInDays($ban->expires_at)))->toBe(3);
});

it('bans permanently when the code allows it', function () {
    assertUserActionSaved(userActionsAs($this->moderator)->post("/admin/users/{$this->member->id}/ban", [
        'type' => 'full', 'reason_code' => 'rules', 'permanent' => true,
    ]));

    expect($this->member->activeBan()?->expires_at)->toBeNull();
});

it('rejects an unknown violation code and invalid durations', function () {
    userActionsAs($this->moderator)->post("/admin/users/{$this->member->id}/ban", [
        'type' => 'full', 'reason_code' => 'unknown', 'expires_in_days' => 3,
    ])->assertSessionHasErrors('reason_code');

    userActionsAs($this->moderator)->post("/admin/users/{$this->member->id}/ban", [
        'type' => 'full', 'reason_code' => 'spam', 'permanent' => true,
    ])->assertSessionHasErrors('expires_at');

    userActionsAs($this->moderator)->post("/admin/users/{$this->member->id}/ban", [
        'type' => 'nope', 'reason_code' => 'spam',
    ])->assertSessionHasErrors(['type', 'expires_in_days']);

    userActionsAs($this->moderator)->postJson("/admin/users/{$this->member->id}/ban", [
        'type' => 'full', 'reason_code' => 'unknown', 'expires_in_days' => 3,
    ])->assertUnprocessable();

    expect($this->member->bans()->count())->toBe(0);
});

it('lifts an active ban', function () {
    $ban = Ban::create(['user_id' => $this->member->id, 'banned_by_id' => $this->admin->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    assertUserActionSaved(userActionsAs($this->moderator)->delete("/admin/users/{$this->member->id}/ban/{$ban->id}"));

    expect($this->member->fresh()->isBanned())->toBeFalse();
});

it('404s when lifting a ban of another user', function () {
    $other = User::factory()->create();
    $ban = Ban::create(['user_id' => $other->id, 'banned_by_id' => $this->admin->id, 'type' => BanType::Full, 'reason_code' => 'spam', 'starts_at' => now()->subMinute()]);

    userActionsAs($this->moderator)->delete("/admin/users/{$this->member->id}/ban/{$ban->id}")->assertNotFound();

    expect($other->fresh()->isBanned())->toBeTrue();
});

it('updates the profile', function () {
    assertUserActionSaved(userActionsAs($this->admin)->patch("/admin/users/{$this->member->id}", [
        'name' => 'Renamed',
        'email' => 'Renamed@Example.test',
        'sex' => 'male',
        'birth_date' => '1991-02-03',
        'about' => null,
        'signature' => null,
        'language' => 'en',
        'timezone' => 'Europe/Moscow',
    ]));

    $fresh = $this->member->fresh();
    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->email)->toBe('renamed@example.test')
        ->and($fresh->language)->toBe('en');
});

it('validates the profile and forbids moderators', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    userActionsAs($this->admin)->patch("/admin/users/{$this->member->id}", [
        'name' => '', 'email' => 'TAKEN@example.test', 'sex' => 'other', 'language' => 'xx', 'timezone' => 'Mars/Base',
    ])->assertSessionHasErrors(['name', 'email', 'sex', 'language', 'timezone']);

    userActionsAs($this->moderator)->patch("/admin/users/{$this->member->id}", [
        'name' => 'X', 'email' => 'x@example.test', 'language' => 'ru', 'timezone' => 'UTC',
    ])->assertForbidden();
});

it('marks a user suspicious', function () {
    assertUserActionSaved(userActionsAs($this->admin)->patch("/admin/users/{$this->member->id}/suspicious", ['is_suspicious' => true, 'note' => 'multi']));

    $fresh = $this->member->fresh();
    expect($fresh->is_suspicious)->toBeTrue()->and($fresh->suspicious_note)->toBe('multi');

    userActionsAs($this->moderator)->patch("/admin/users/{$this->member->id}/suspicious", ['is_suspicious' => false])
        ->assertForbidden();
});

it('changes the login', function () {
    assertUserActionSaved(userActionsAs($this->admin)->patch("/admin/users/{$this->member->id}/login", ['login' => 'Fresh_Login']));

    expect($this->member->fresh()->login)->toBe('fresh_login')
        ->and(Activity::query()->where('event', 'user.login_changed')->where('causer_id', $this->admin->id)->exists())->toBeTrue();
});

it('rejects a taken login and forbids moderators', function () {
    User::factory()->create(['login' => 'occupied']);

    userActionsAs($this->admin)->patch("/admin/users/{$this->member->id}/login", ['login' => 'occupied'])
        ->assertSessionHasErrors('login');
    userActionsAs($this->admin)->patchJson("/admin/users/{$this->member->id}/login", ['login' => 'occupied'])
        ->assertUnprocessable();
    userActionsAs($this->moderator)->patch("/admin/users/{$this->member->id}/login", ['login' => 'another'])
        ->assertForbidden();

    expect($this->member->fresh()->login)->toBe('target_user');
});

it('deletes a user and redirects to the list', function () {
    userActionsAs($this->admin)->delete("/admin/users/{$this->member->id}")
        ->assertRedirect('/admin/users')
        ->assertInertiaFlash('toast.type', 'success');

    expect($this->member->fresh()->trashed())->toBeTrue();
});

it('refuses to delete the last creator with a general error', function () {
    $creator = User::factory()->create()->assignRole('creator');
    $mainAdmin = User::factory()->create()->assignRole('main_admin');

    userActionsAs($mainAdmin)->delete("/admin/users/{$creator->id}")
        ->assertRedirect('/admin/users/'.$this->member->id)
        ->assertSessionHasErrors('general');

    expect($creator->fresh()->trashed())->toBeFalse();
});

it('forbids moderators from deleting users', function () {
    userActionsAs($this->moderator)->delete("/admin/users/{$this->member->id}")->assertForbidden();

    expect($this->member->fresh()->trashed())->toBeFalse();
});
