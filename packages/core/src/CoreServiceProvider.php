<?php

declare(strict_types=1);

namespace Ladcms\Core;

use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Ladcms\Core\Admin\AdminMenu;
use Ladcms\Core\Admin\DashboardStats;
use Ladcms\Core\Admin\MenuItem;
use Ladcms\Core\Console\InstallModuleCommand;
use Ladcms\Core\Console\PruneUnverifiedUsersCommand;
use Ladcms\Core\Console\UninstallModuleCommand;
use Ladcms\Core\Console\UpdateModulesCommand;
use Ladcms\Core\Http\Middleware\AdminAccess;
use Ladcms\Core\Http\Middleware\CheckBan;
use Ladcms\Core\Http\Middleware\ConfirmPasswordWithSettingsTtl;
use Ladcms\Core\Http\Middleware\EnsureRegistrationOpen;
use Ladcms\Core\Http\Middleware\SetLocale;
use Ladcms\Core\Http\Middleware\SharePublicLayout;
use Ladcms\Core\Http\Middleware\TrackOnline;
use Ladcms\Core\Http\Resources\CurrentUserResource;
use Ladcms\Core\Inertia\ModulePageFinder;
use Ladcms\Core\Listeners\AssignDefaultRole;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasAdminPanel;
use Ladcms\Core\Module\Contracts\HasHeaderActions;
use Ladcms\Core\Module\Contracts\HasNavigation;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\Contracts\HasSettings;
use Ladcms\Core\Module\Contracts\HasWidgets;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Policies\RolePolicy;
use Ladcms\Core\Policies\UserPolicy;
use Ladcms\Core\Services\LocaleManager;
use Ladcms\Core\Settings\AppearanceSettings;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\ModerationSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Support\UserModel;
use Ladcms\Core\Ui\HeaderActions;
use Ladcms\Core\Ui\Navigation;
use Ladcms\Core\Ui\ProfileSections;
use Ladcms\Core\Ui\Widgets;
use Spatie\LaravelPackageTools\Package;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\PermissionRegistrar;

class CoreServiceProvider extends ModuleServiceProvider implements HasAdminPanel, HasHeaderActions, HasNavigation, HasPermissions, HasSettings, HasWidgets
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

    /**
     * npm packages required by the core UI kit (beyond the starter kit's own dependencies).
     *
     * @return array<string, string>
     */
    public function npmDependencies(): array
    {
        return [
            '@radix-ui/react-alert-dialog' => '^1.2.1',
            '@radix-ui/react-popover' => '^1.3.1',
            '@radix-ui/react-switch' => '^1.4.1',
            '@radix-ui/react-tabs' => '^1.2.1',
            '@tailwindcss/typography' => '^0.5.20',
            'cmdk' => '^1.1.1',
        ];
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

    /**
     * Разделы админки ядра. Единственный источник для AdminMenu; дочерние пункты `settings`
     * AdminMenu достраивает сам — по группам настроек всех HasSettings-модулей.
     * `path` — url для разделов, чьи роуты регистрируются позже.
     */
    public function adminMenu(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'core::admin.menu.dashboard', 'icon' => 'layout-dashboard', 'route' => 'admin.dashboard', 'order' => 10, 'path' => '/admin'],
            ['key' => 'users', 'label' => 'core::admin.menu.users', 'icon' => 'users', 'route' => 'admin.users.index', 'order' => 20, 'path' => '/admin/users', 'permission' => 'users.view', 'children' => [
                ['key' => 'all', 'label' => 'core::admin.menu.users_all', 'route' => 'admin.users.index', 'path' => '/admin/users'],
                ['key' => 'suspicious', 'label' => 'core::admin.menu.users_suspicious', 'route' => 'admin.users.index', 'path' => '/admin/users', 'params' => ['status' => 'suspicious']],
            ]],
            ['key' => 'roles', 'label' => 'core::admin.menu.roles', 'icon' => 'shield', 'route' => 'admin.roles.index', 'order' => 30, 'path' => '/admin/roles', 'permission' => 'roles.manage'],
            ['key' => 'moderation', 'label' => 'core::admin.menu.moderation', 'icon' => 'gavel', 'route' => 'admin.moderation.complaints.index', 'order' => 40, 'path' => '/admin/moderation/complaints', 'permission' => 'complaints.review', 'children' => [
                ['key' => 'complaints', 'label' => 'core::admin.menu.complaints', 'route' => 'admin.moderation.complaints.index', 'path' => '/admin/moderation/complaints', 'permission' => 'complaints.review'],
                ['key' => 'bans', 'label' => 'core::admin.menu.bans', 'route' => 'admin.moderation.bans.index', 'path' => '/admin/moderation/bans', 'permission' => 'users.ban'],
            ]],
            ['key' => 'settings', 'label' => 'core::admin.menu.settings', 'icon' => 'settings', 'route' => 'admin.settings.edit', 'order' => 50, 'path' => '/admin/settings/general', 'params' => ['group' => 'general'], 'permission' => 'settings.manage'],
            ['key' => 'modules', 'label' => 'core::admin.menu.modules', 'icon' => 'puzzle', 'route' => 'admin.modules.index', 'order' => 60, 'path' => '/admin/modules', 'permission' => 'modules.manage'],
            ['key' => 'activity', 'label' => 'core::admin.menu.activity', 'icon' => 'scroll-text', 'route' => 'admin.activity.index', 'order' => 70, 'path' => '/admin/activity', 'permission' => 'activity.view'],
        ];
    }

    public function navigation(): array
    {
        return [
            ['key' => 'home', 'label' => 'core::public.nav.home', 'icon' => 'house', 'route' => 'home', 'order' => 10],
            ['key' => 'users', 'label' => 'core::public.nav.users', 'icon' => 'users', 'route' => 'users.index', 'order' => 20],
            ['key' => 'rules', 'label' => 'core::public.nav.rules', 'icon' => 'scroll', 'route' => 'rules', 'order' => 30],
        ];
    }

    public function widgets(): array
    {
        $avatar = fn (User $user): array => [
            'id' => $user->id,
            'login' => $user->login,
            'display_name' => $user->name ?: $user->login,
            'avatar_url' => $user->avatar_url,
        ];

        return [
            ['name' => 'welcome', 'component' => 'core::Welcome', 'slot' => 'main', 'order' => 10, 'props' => fn (): array => [
                'site_name' => app(GeneralSettings::class)->site_name,
                'description' => app(GeneralSettings::class)->site_description ?: null,
                'rules_url' => route('rules'),
            ]],
            ['name' => 'online', 'component' => 'core::Online', 'slot' => 'sidebar', 'order' => 10, 'props' => function () use ($avatar): array {
                $online = UserModel::query()->where('last_activity_at', '>=', now()->subMinutes(DashboardStats::ONLINE_MINUTES));

                return [
                    'count' => (clone $online)->count(),
                    'users' => $online->orderByDesc('last_activity_at')->limit(10)->get()->map($avatar)->all(),
                ];
            }],
            ['name' => 'latest-users', 'component' => 'core::LatestUsers', 'slot' => 'sidebar', 'order' => 20, 'props' => fn (): array => [
                'users' => UserModel::query()->latest('id')->limit(5)->get()
                    ->map(fn (User $user): array => $avatar($user) + ['created_at' => $user->created_at?->toISOString()])->all(),
            ]],
        ];
    }

    public function headerActions(): array
    {
        return [['key' => 'notifications', 'icon' => 'bell', 'component' => 'core::NotificationBell', 'order' => 10, 'auth' => true]];
    }

    /** Админ-роуты ядра — прямо под `/admin` с именами `admin.*`. */
    protected function adminRoutePrefix(): string
    {
        return 'admin';
    }

    protected function adminRouteNamePrefix(): string
    {
        return 'admin.';
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
        $router->aliasMiddleware('ladcms.password-confirm', ConfirmPasswordWithSettingsTtl::class);

        // CheckBan, TrackOnline и EnsureRegistrationOpen — в группу `web`. Через HTTP-kernel: он синхронизирует свои группы в роутер
        // при создании и затёр бы правку, внесённую только в роутер.
        $kernel = $this->app->make(HttpKernelContract::class);
        // @phpstan-ignore instanceof.alwaysTrue (Larastan выводит тип из Testbench; в приложении kernel может быть любым)
        if ($kernel instanceof HttpKernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
            $kernel->appendMiddlewareToGroup('web', CheckBan::class);
            $kernel->appendMiddlewareToGroup('web', TrackOnline::class);
            $kernel->appendMiddlewareToGroup('web', EnsureRegistrationOpen::class);
            $kernel->appendMiddlewareToGroup('web', SetLocale::class);
            $kernel->appendMiddlewareToGroup('web', SharePublicLayout::class);
        } else {
            $router->pushMiddlewareToGroup('web', CheckBan::class);
            $router->pushMiddlewareToGroup('web', TrackOnline::class);
            $router->pushMiddlewareToGroup('web', EnsureRegistrationOpen::class);
            $router->pushMiddlewareToGroup('web', SetLocale::class);
            $router->pushMiddlewareToGroup('web', SharePublicLayout::class);
        }

        $this->app->scoped(Widgets::class);
        $this->app->scoped(Navigation::class);
        $this->app->scoped(HeaderActions::class);
        $this->app->scoped(ProfileSections::class);

        Inertia::share('locale', fn (): string => app()->getLocale());
        Inertia::share('locales', fn (): array => $this->app->make(LocaleManager::class)->available());

        // Меню и текущий пользователь админки — только на админ-роутах.
        Inertia::share('admin', function (): ?array {
            $request = $this->app->make(Request::class);
            $user = $request->user();
            if (! $user instanceof User || ! $request->routeIs('admin.*')) {
                return null;
            }

            return [
                'menu' => array_map(
                    fn (MenuItem $item): array => $item->toArray(),
                    $this->app->make(AdminMenu::class)->build($user, $request),
                ),
                'user' => CurrentUserResource::make($user)->resolve($request),
            ];
        });

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

        // Морф-алиас пользователя (см. User::getMorphClass): роли, уведомления и журнал хранят `user`,
        // а при чтении morphTo получает модель приложения из `auth.providers.users.model`.
        Relation::morphMap([UserModel::MORPH_ALIAS => UserModel::class()]);

        // `{user}` в роутах — модель приложения (`auth.providers.users.model`), мягко удалённые → 404.
        Route::bind('user', function (string $value): User {
            if (! ctype_digit($value)) {
                throw (new ModelNotFoundException)->setModel(UserModel::class(), [$value]);
            }

            return UserModel::query()->findOrFail((int) $value);
        });

        // Страницы модулей: Inertia-компонент `module::Path` → <modulePath>/resources/js/pages/Path.tsx.
        $this->app->extend('inertia.view-finder', fn ($finder, $app) => new ModulePageFinder(
            $finder,
            $app->make(ModuleRegistry::class),
        ));
    }
}
