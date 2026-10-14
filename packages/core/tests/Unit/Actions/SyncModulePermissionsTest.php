<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Actions\Modules\SyncModulePermissions;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('creates permissions declared by the module idempotently', function () {
    $module = $this->app->register(DummyServiceProvider::class);

    expect((new SyncModulePermissions)->handle($module))->toBe(['dummy.view'])
        ->and(Permission::where('name', 'dummy.view')->exists())->toBeTrue()
        ->and((new SyncModulePermissions)->handle($module))->toBe([]);
});
