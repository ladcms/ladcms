<?php

declare(strict_types=1);

namespace Ladcms\Core;

use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Ladcms\Core\Console\InstallModuleCommand;
use Ladcms\Core\Console\PruneUnverifiedUsersCommand;
use Ladcms\Core\Console\UninstallModuleCommand;
use Ladcms\Core\Console\UpdateModulesCommand;
use Ladcms\Core\Http\Middleware\AdminAccess;
use Ladcms\Core\Http\Middleware\CheckBan;
use Ladcms\Core\Http\Middleware\EnsureRegistrationOpen;
use Ladcms\Core\Http\Middleware\SetLocale;
use Ladcms\Core\Http\Middleware\TrackOnline;
use Ladcms\Core\Inertia\ModulePageFinder;
use Ladcms\Core\Listeners\AssignDefaultRole;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\Contracts\HasSettings;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Policies\RolePolicy;
use Ladcms\Core\Policies\UserPolicy;
use Ladcms\Core\Settings\AppearanceSettings;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\ModerationSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Spatie\LaravelPackageTools\Package;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\PermissionRegistrar;

class CoreServiceProvider extends ModuleServiceProvider implements HasPermissions, HasSettings
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

    public function settings(): array
    {
        return [GeneralSettings::class, RegistrationSettings::class, ModerationSettings::class, AppearanceSettings::class];
    }

    protected function configureModule(Package $package): void
    {
        $package->hasCommands([
            InstallModuleCommand::class,
            UpdateModulesCommand::class,
            UninstallModuleCommand::class,
            PruneUnverifiedUsersCommand::class,
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
        $router->aliasMiddleware('ladcms.ban', CheckBan::class);

        // CheckBan, TrackOnline и EnsureRegistrationOpen — в группу `web`. Через HTTP-kernel: он синхронизирует свои группы в роутер
        // при создании и затёр бы правку, внесённую только в роутер.
        $kernel = $this->app->make(HttpKernelContract::class);
        // @phpstan-ignore instanceof.alwaysTrue (Larastan выводит тип из Testbench; в приложении kernel может быть любым)
        if ($kernel instanceof HttpKernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
            $kernel->appendMiddlewareToGroup('web', CheckBan::class);
            $kernel->appendMiddlewareToGroup('web', TrackOnline::class);
            $kernel->appendMiddlewareToGroup('web', EnsureRegistrationOpen::class);
            $kernel->appendMiddlewareToGroup('web', SetLocale::class);
        } else {
            $router->pushMiddlewareToGroup('web', CheckBan::class);
            $router->pushMiddlewareToGroup('web', TrackOnline::class);
            $router->pushMiddlewareToGroup('web', EnsureRegistrationOpen::class);
            $router->pushMiddlewareToGroup('web', SetLocale::class);
        }

        Inertia::share('locale', fn (): string => app()->getLocale());
        Inertia::share('locales', fn (): mixed => config('core.locales'));

        // Ежедневная чистка: неподтверждённые аккаунты и старые записи журнала активности.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('ladcms:prune-unverified')->daily();
            $schedule->command('activitylog:clean --force')->daily();
        });

        // Роль регистрации — новым пользователям (событие диспатчит Fortify после CreateNewUser).
        Event::listen(Registered::class, AssignDefaultRole::class);

        // Создатель проходит любые простые проверки (permission/ability без модели). Проверки с моделью
        // идут через политики и для него: так системные роли и сам создатель защищены от удаления.
        Gate::before(fn ($user, string $ability, array $arguments = []): ?bool => $user instanceof User && $user->isCreator() && $arguments === [] ? true : null);

        // Политики ядра. Gate::getPolicyFor() сам находит политику для наследников (is_subclass_of),
        // поэтому App\Models\User extends core User отдельной регистрации не требует.
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        // Страницы модулей: Inertia-компонент `module::Path` → <modulePath>/resources/js/pages/Path.tsx.
        $this->app->extend('inertia.view-finder', fn ($finder, $app) => new ModulePageFinder(
            $finder,
            $app->make(ModuleRegistry::class),
        ));
    }
}
