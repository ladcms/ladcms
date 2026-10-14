<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('web')->get('/probe', fn () => 'ok')->name('probe');
    Route::middleware('web')->post('/probe', fn () => 'posted');
});

function banned(BanType $type, ?DateTimeInterface $expires = null): User
{
    $user = User::factory()->create();
    Ban::create(['user_id' => $user->id, 'banned_by_id' => User::factory()->create()->id, 'type' => $type, 'reason_code' => 'spam', 'starts_at' => now()->subMinute(), 'expires_at' => $expires]);

    return $user;
}

it('redirects fully banned users to /banned and blocks posts', function () {
    $user = banned(BanType::Full, now()->addDay());

    $this->actingAs($user)->get('/probe')->assertRedirect('/banned');
    $this->actingAs($user)->post('/probe')->assertRedirect('/banned');
    $this->actingAs($user)->postJson('/probe')->assertForbidden();
});

it('lets readonly-banned users browse', function () {
    $this->actingAs(banned(BanType::Readonly))->get('/probe')->assertOk();
});

it('ignores expired bans and guests', function () {
    $this->actingAs(banned(BanType::Full, now()->subHour()))->get('/probe')->assertOk();
    $this->get('/probe')->assertOk();
});

it('shows the banned page only to banned users', function () {
    $user = banned(BanType::Full, now()->addDay());

    $this->actingAs($user)->get('/banned')->assertOk()->assertInertia(fn ($page) => $page->component('core::banned')->where('reason_code', 'spam'));
    $this->actingAs(User::factory()->create())->get('/banned')->assertRedirect('/');
});
