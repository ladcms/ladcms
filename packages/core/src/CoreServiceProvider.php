<?php

declare(strict_types=1);

namespace Ladcms\Core;

use Illuminate\Support\Facades\Gate;
use Ladcms\Core\Console\InstallModuleCommand;
use Ladcms\Core\Console\UninstallModuleCommand;
use Ladcms\Core\Console\UpdateModulesCommand;
use Ladcms\Core\Http\Middleware\AdminAccess;
use Ladcms\Core\Inertia\ModulePageFinder;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\PermissionRegistrar;

class CoreServiceProvider extends ModuleServiceProvider implements HasPermissions
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

    /** @return array<string, string> */
    public function permissions(): array
    {
        $names = ['admin.access', 'users.view', 'users.edit', 'users.ban', 'users.delete', 'users.assign-roles',
            'roles.manage', 'settings.manage', 'modules.manage', 'complaints.review', 'activity.view'];

        return array_combine($names, array_map(fn (string $n): string => 'core::permissions.'.$n, $names));
    }

    protected function configureModule(Package $package): void
    {
        $package->hasCommands([
            InstallModuleCommand::class,
            UpdateModulesCommand::class,
            UninstallModuleCommand::class,
        ]);
    }

    public function registeringPackage(): void
    {
        // Spatie читает модель роли из конфига (регистратор, route model bindings) — подменяем на нашу.
        // Отложено до booting: при package discovery core регистрируется раньше spatie, а её
        // mergeConfigFrom мержит неглубоко — ранний config() затёр бы весь `permission.models`.
        $this->app->booting(fn () => config(['permission.models.role' => Role::class]));
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        // Если PermissionRegistrar уже создан до нашего config(), переопределяем класс явно.
        if ($this->app->resolved(PermissionRegistrar::class)) {
            $this->app->make(PermissionRegistrar::class)->setRoleClass(Role::class);
        }

        // Проверка версий/зависимостей — после boot всех провайдеров.
        $this->app->booted(fn () => $this->app->make(ModuleRegistry::class)->validate());

        // Spatie 8 не регистрирует алиасы своих middleware — делаем это в ядре.
        $router = $this->app['router'];
        $router->aliasMiddleware('admin', AdminAccess::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);

        // Создатель может всё, включая abilities без permission/policy.
        Gate::before(fn ($user, string $ability): ?bool => $user instanceof User && $user->isCreator() ? true : null);

        // Страницы модулей: Inertia-компонент `module::Path` → <modulePath>/resources/js/pages/Path.tsx.
        $this->app->extend('inertia.view-finder', fn ($finder, $app) => new ModulePageFinder(
            $finder,
            $app->make(ModuleRegistry::class),
        ));
    }
}
