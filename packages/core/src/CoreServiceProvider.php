<?php

namespace Ladcms\Core;

use Ladcms\Core\Http\Middleware\AdminAccess;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;

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

    public function packageBooted(): void
    {
        parent::packageBooted();

        // Проверка версий/зависимостей — после boot всех провайдеров.
        $this->app->booted(fn () => $this->app->make(ModuleRegistry::class)->validate());

        // Временная заглушка middleware "admin"; план core заменит её на проверку permission.
        $this->app['router']->aliasMiddleware('admin', AdminAccess::class);
    }
}
