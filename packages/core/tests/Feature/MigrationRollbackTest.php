<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('rolls back and re-applies core migrations', function () {
    $this->artisan('migrate:rollback')->assertSuccessful();

    expect(Schema::hasTable('roles') && Schema::hasColumn('roles', 'level'))->toBeFalse()
        ->and(Schema::hasTable('users') && Schema::hasColumn('users', 'login'))->toBeFalse();

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasColumn('roles', 'level'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'login'))->toBeTrue();
});
