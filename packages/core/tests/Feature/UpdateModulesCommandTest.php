<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Без RefreshDatabase: ladcms:update вызывается из post-update-cmd до запуска миграций.

it('skips the update when the modules table does not exist', function () {
    Schema::dropIfExists('modules');

    $this->artisan('ladcms:update')
        ->expectsOutputToContain('LADCMS is not installed yet (modules table missing); skipping module update.')
        ->assertSuccessful();
});

it('skips the update when the database is unavailable', function () {
    config()->set('database.connections.testing.database', '/nonexistent/dir/db.sqlite');
    DB::purge('testing');

    $this->artisan('ladcms:update')
        ->expectsOutputToContain('Database unavailable')
        ->assertSuccessful();
});
