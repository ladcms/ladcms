<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Support;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Ladcms\Core\CoreServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * Core регистрируется раньше spatie/laravel-permission — как при package discovery
 * (ladcms/core идёт раньше в installed.json). Трейт, а не подкласс TestCase (см. ReversedProviderOrder).
 */
trait CoreBeforeSpatieProviderOrder
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            InertiaServiceProvider::class,
            PermissionServiceProvider::class,
            LaravelSettingsServiceProvider::class,
        ];
    }
}
