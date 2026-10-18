<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Users\DeleteOwnAccount;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\Audit;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('soft deletes the account and writes an audit entry', function () {
    $user = User::factory()->create()->assignRole('user');

    app(DeleteOwnAccount::class)->handle($user);

    $activity = Activity::query()->sole();

    expect($user->fresh()?->trashed())->toBeTrue()
        ->and($activity->log_name)->toBe(Audit::LOG_NAME)
        ->and($activity->event)->toBe('user.deleted_own_account')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($user->id);
});

it('refuses to delete the last creator', function () {
    $creator = User::factory()->create()->assignRole('creator');

    expect(fn () => app(DeleteOwnAccount::class)->handle($creator))->toThrow(InvalidArgumentException::class);

    expect($creator->fresh()?->trashed())->toBeFalse()
        ->and(Activity::query()->count())->toBe(0);
});

it('lets a creator leave while another creator remains', function () {
    $creator = User::factory()->create()->assignRole('creator');
    User::factory()->create()->assignRole('creator');

    app(DeleteOwnAccount::class)->handle($creator);

    expect($creator->fresh()?->trashed())->toBeTrue();
});
