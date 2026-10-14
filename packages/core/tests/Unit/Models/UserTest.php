<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Models\User;

uses(RefreshDatabase::class);

it('creates a user with ladcms fields and defaults', function () {
    $user = User::factory()->create(['login' => 'vadim', 'name' => 'Vadim', 'sex' => Sex::Male]);

    expect($user->login)->toBe('vadim')
        ->and($user->sex)->toBe(Sex::Male)
        ->and($user->language)->toBe('ru')
        ->and($user->timezone)->toBe('UTC')
        ->and($user->is_suspicious)->toBeFalse()
        ->and($user->show_email)->toBeFalse()
        ->and($user->messages_from)->toBe(MessagesFrom::All)
        ->and($user->display_name)->toBe('Vadim');
});

it('falls back to login as display name', function () {
    $user = User::factory()->create(['login' => 'anna', 'name' => '']);

    expect($user->display_name)->toBe('anna');
});

it('enforces unique login', function () {
    User::factory()->create(['login' => 'dup']);

    expect(fn () => User::factory()->create(['login' => 'dup']))->toThrow(QueryException::class);
});

it('soft deletes users', function () {
    $user = User::factory()->create();
    $user->delete();

    expect(User::count())->toBe(0)->and(User::withTrashed()->count())->toBe(1);
});
