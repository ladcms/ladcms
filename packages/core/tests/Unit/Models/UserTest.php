<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('does not mass-assign moderation and audit fields', function () {
    $user = new User(['login' => 'someone', 'registered_ip' => '10.0.0.1', 'is_suspicious' => true, 'suspicious_note' => 'x']);

    expect($user->registered_ip)->toBeNull()
        ->and($user->is_suspicious)->toBeFalse()
        ->and($user->suspicious_note)->toBeNull()
        ->and($user->login)->toBe('someone');
});

it('records first_verified_at the first time the email gets verified and never overwrites it', function () {
    $user = User::factory()->unverified()->create();
    expect($user->fresh()->first_verified_at)->toBeNull();

    $this->travelTo(now()->subDay());
    $user->forceFill(['email_verified_at' => now()])->save();
    $first = $user->fresh()->first_verified_at;
    $this->travelBack();
    expect($first)->not->toBeNull()
        ->and($first?->toIso8601String())->toBe($user->fresh()->email_verified_at?->toIso8601String());

    $user->forceFill(['email_verified_at' => null])->save();
    $user->forceFill(['email_verified_at' => now()])->save();

    expect($user->fresh()->first_verified_at?->toIso8601String())->toBe($first?->toIso8601String());
});

it('sets first_verified_at for users created already verified', function () {
    $user = User::factory()->create();

    expect($user->fresh()->first_verified_at)->not->toBeNull();
});

it('backfills first_verified_at from email_verified_at in the migration', function () {
    $migration = require __DIR__.'/../../../database/migrations/2026_10_18_000002_add_first_verified_at_to_users_table.php';
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    $migration->down();
    $migration->up();

    $row = fn (User $u) => DB::table('users')->where('id', $u->id)->first();
    expect($row($verified)->first_verified_at)->not->toBeNull()
        ->and((string) $row($verified)->first_verified_at)->toBe((string) $row($verified)->email_verified_at)
        ->and($row($unverified)->first_verified_at)->toBeNull();
});
