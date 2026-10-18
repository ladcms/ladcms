<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Http\Resources\Admin\BanResource;
use Ladcms\Core\Http\Resources\Admin\UserDetailResource;
use Ladcms\Core\Http\Resources\Admin\UserListResource;
use Ladcms\Core\Http\Resources\Admin\UserListResource as ListRes;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

function makeBan(User $user, array $attrs = []): Ban
{
    return Ban::create($attrs + [
        'user_id' => $user->id, 'type' => BanType::cases()[0], 'reason_code' => 'spam',
        'starts_at' => now()->subDay(), 'expires_at' => null,
    ]);
}

it('shapes the user list resource', function () {
    $data = new UserListResource(User::factory()->create())->resolve(Request::create('/x'));

    expect(array_keys($data))->toBe([
        'id', 'login', 'name', 'display_name', 'email', 'avatar_url', 'roles',
        'is_online', 'is_banned', 'is_suspicious', 'created_at', 'last_activity_at',
    ]);
});

it('detail resource has null active_ban without a ban', function () {
    $data = new UserDetailResource(User::factory()->create())->resolve(Request::create('/x'));

    expect($data['active_ban'])->toBeNull()
        ->and($data['bans_count'])->toBe(0)
        ->and($data['complaints_count'])->toBe(0)
        ->and($data)->toHaveKeys(['sex', 'timezone', 'suspicious_note', 'email_verified_at']);
});

it('detail resource includes the active ban', function () {
    $user = User::factory()->create();
    makeBan($user);

    $data = new UserDetailResource($user)->resolve(Request::create('/x'));

    expect($data['active_ban']['is_active'])->toBeTrue()
        ->and($data['active_ban']['user']['login'])->toBe($user->login)
        ->and($data['bans_count'])->toBe(1);
});

it('ban resource reports is_active', function () {
    $user = User::factory()->create();
    $expired = makeBan($user, ['starts_at' => now()->subDays(3), 'expires_at' => now()->subDay()]);

    $data = new BanResource($expired)->resolve(Request::create('/x'));

    expect($data['is_active'])->toBeFalse()->and($data['banned_by'])->toBeNull();
});

it('resolves a user list without per-row queries', function () {
    $this->seed(RolesSeeder::class);
    $users = User::factory()->count(3)->create();
    $users->each(fn (User $u) => $u->assignRole('user'));
    makeBan($users[0]);

    $loaded = User::with('roles')->withActiveBanFlag()->get();

    DB::enableQueryLog();
    $rows = ListRes::collection($loaded)->resolve(Request::create('/x'));
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBe(0)
        ->and(collect($rows)->where('is_banned', true))->toHaveCount(1);
});
