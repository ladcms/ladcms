<?php

declare(strict_types=1);

namespace Ladcms\Tests;

use Illuminate\Support\Facades\View;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Models\User;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

use function Orchestra\Testbench\default_migration_path;
use function Orchestra\Testbench\laravel_or_fail;
use function Orchestra\Testbench\load_migration_paths;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            PermissionServiceProvider::class,
            LaravelSettingsServiceProvider::class,
            ActivitylogServiceProvider::class,
            CoreServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('inertia.pages.ensure_pages_exist', false);
        $app['config']->set('inertia.testing.ensure_pages_exist', false);

        // Корневой Blade-шаблон Inertia (`app`) для рендера страниц в Testbench.
        View::addLocation(__DIR__.'/views');
    }

    /**
     * Стандартные миграции Laravel (users, sessions, cache, jobs) из скелета Testbench
     * регистрируются в мигратор, а не запускаются сразу: тогда RefreshDatabase применяет
     * их вместе с миграциями модулей в порядке таймстампов (users раньше core-ALTER).
     * Трейт WithLaravelMigrations здесь не годится: без testbench.yaml (workbench.install) он no-op.
     */
    protected function defineDatabaseMigrations(): void
    {
        load_migration_paths(laravel_or_fail($this->app), default_migration_path());
    }
}
