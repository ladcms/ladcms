<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->me = User::factory()->create(['login' => 'zed_me', 'name' => 'Zed']);
});

it('rejects guests', function () {
    $this->getJson('/api/users/mention-search?q=va')->assertUnauthorized();
});

it('finds users by login or name prefix', function () {
    $vadim = User::factory()->create(['login' => 'vadim', 'name' => 'Vadim D', 'avatar_path' => 'avatars/v.png']);
    $byName = User::factory()->create(['login' => 'qwerty', 'name' => 'Valentina']);
    User::factory()->create(['login' => 'anna', 'name' => 'Anna']);
    User::factory()->create(['login' => 'ivan', 'name' => 'Ivan']); // contains "va" but not as prefix

    $response = $this->actingAs($this->me)->getJson('/api/users/mention-search?q=va')->assertOk();

    expect(collect($response->json())->pluck('login')->all())->toBe(['qwerty', 'vadim'])
        ->and($response->json('1'))->toBe([
            'id' => $vadim->id,
            'login' => 'vadim',
            'name' => 'Vadim D',
            'avatar_url' => $vadim->avatar_url,
        ])
        ->and($response->json('0.avatar_url'))->toBeNull()
        ->and($response->json('1.avatar_url'))->toBeString();
    expect($byName->id)->toBe($response->json('0.id'));
});

it('excludes soft-deleted users', function () {
    User::factory()->create(['login' => 'vadim_gone'])->delete();

    $this->actingAs($this->me)->getJson('/api/users/mention-search?q=vadim')->assertOk()->assertExactJson([]);
});

it('escapes LIKE wildcards', function () {
    User::factory()->create(['login' => 'abc', 'name' => 'Abc']);

    $this->actingAs($this->me)->getJson('/api/users/mention-search?q=%25')->assertOk()->assertExactJson([]);
    $this->actingAs($this->me)->getJson('/api/users/mention-search?q=_bc')->assertOk()->assertExactJson([]);
});

it('limits results to 10', function () {
    foreach (range(1, 12) as $i) {
        User::factory()->create(['login' => sprintf('val_%02d', $i)]);
    }

    $response = $this->actingAs($this->me)->getJson('/api/users/mention-search?q=val')->assertOk();

    expect($response->json())->toHaveCount(10)
        ->and($response->json('0.login'))->toBe('val_01');
});

it('returns an empty list for an empty query', function () {
    User::factory()->create(['login' => 'vadim']);

    $this->actingAs($this->me)->getJson('/api/users/mention-search?q=')->assertOk()->assertExactJson([]);
    $this->actingAs($this->me)->getJson('/api/users/mention-search')->assertOk()->assertExactJson([]);
});

it('rejects too long queries', function () {
    $this->actingAs($this->me)->getJson('/api/users/mention-search?q='.str_repeat('a', 33))->assertUnprocessable();
});
