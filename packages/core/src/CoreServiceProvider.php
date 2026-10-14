<?php

declare(strict_types=1);

namespace Ladcms\Core;

use Ladcms\Core\Console\InstallModuleCommand;
use Ladcms\Core\Console\UninstallModuleCommand;
use Ladcms\Core\Console\UpdateModulesCommand;
use Ladcms\Core\Http\Middleware\AdminAccess;
use Ladcms\Core\Inertia\ModulePageFinder;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Spatie\LaravelPackageTools\Package;

class CoreServiceProvider extends ModuleServiceProvider
{
    public const VERSION = '0.1.0';

    public function name(): string
    {
        return 'core';
    }

    public function version(): string
    {
        return self::VERSION;
    }

    protected function configureModule(Package $package): void
    {
        $package->hasCommands([
            InstallModuleCommand::class,
            UpdateModulesCommand::class,
            UninstallModuleCommand::class,
        ]);
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        // Проверка версий/зависимостей — после boot всех провайдеров.
        $this->app->booted(fn () => $this->app->make(ModuleRegistry::class)->validate());

        // Временная заглушка middleware "admin"; план core заменит её на проверку permission.
        $this->app['router']->aliasMiddleware('admin', AdminAccess::class);

        // Страницы модулей: Inertia-компонент `module::Path` → <modulePath>/resources/js/pages/Path.tsx.
        $this->app->extend('inertia.view-finder', fn ($finder, $app) => new ModulePageFinder(
            $finder,
            $app->make(ModuleRegistry::class),
        ));
    }
}
