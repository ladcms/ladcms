<?php

declare(strict_types=1);

namespace Ladcms\Core\Module;

use Illuminate\Support\Facades\Route;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Module\Concerns\RegistersSettings;
use Ladcms\Core\Module\Contracts\ModuleInterface;
use ReflectionClass;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

abstract class ModuleServiceProvider extends PackageServiceProvider implements ModuleInterface
{
    use RegistersSettings;

    public function coreVersion(): string
    {
        return '*';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function npmDependencies(): array
    {
        return [];
    }

    public function install(): void {}

    public function uninstall(): void {}

    /** Абсолютный путь внутри пакета модуля. */
    public function modulePath(string $relative = ''): string
    {
        return rtrim($this->moduleRoot(), '/').($relative !== '' ? '/'.ltrim($relative, '/') : '');
    }

    /**
     * Корень пакета модуля. По умолчанию провайдер лежит в `src/`, значит корень — на уровень выше.
     * Модуль с другой раскладкой (например, тестовая фикстура) переопределяет этот метод.
     */
    protected function moduleRoot(): string
    {
        return dirname((string) new ReflectionClass($this)->getFileName(), 2);
    }

    public function configurePackage(Package $package): void
    {
        $package->name($this->name());

        $this->configureModule($package);
    }

    /**
     * Точка расширения для модуля (команды, доп. ассеты).
     *
     * Ресурсы по соглашению LADCMS (config, migrations, lang, views, routes) грузятся
     * провайдером сам по modulePath(), а не через package-tools: тот резолвит пути
     * как dirname(provider)/../ и ищет переводы в resources/lang.
     */
    protected function configureModule(Package $package): void {}

    /**
     * Реестр биндится здесь, а не в CoreServiceProvider: package discovery регистрирует
     * провайдеры в порядке installed.json, и модуль может оказаться раньше core.
     *
     * @return $this
     */
    public function register()
    {
        $this->app->singletonIf(ModuleRegistry::class, fn () => new ModuleRegistry(CoreServiceProvider::VERSION));

        parent::register();

        // Классы настроек из HasSettings — scoped-биндинги с данными из БД.
        $this->registerModuleSettings();

        return $this;
    }

    public function packageRegistered(): void
    {
        // Конфиг мержится в фазе register (конвенция Laravel), чтобы другие провайдеры могли читать его в register()/boot().
        $config = $this->modulePath("config/{$this->name()}.php");
        if (is_file($config)) {
            $this->mergeConfigFrom($config, $this->name());
        }

        $this->app->make(ModuleRegistry::class)->register($this);
    }

    /**
     * Модуль, не прошедший проверку версий/зависимостей, не загружается вовсе:
     * ни миграции, ни переводы, ни маршруты, ни ресурсы package-tools, а его конфиг удаляется.
     *
     * В реальном приложении все провайдеры регистрируются до boot, так что решение
     * принимается по полному набору модулей. Если зависимость регистрируется уже после
     * boot (только в тестах через $app->register()), пропущенный модуль остаётся
     * недоступным: реестр помечает его как skipped навсегда.
     *
     * @return $this
     */
    public function boot()
    {
        $registry = $this->app->make(ModuleRegistry::class);

        if (array_key_exists($this->name(), $registry->failures())) {
            $registry->markSkipped($this->name());
            // Конфиг уже смержен в register(), а модуль не загружается — убираем его, чтобы не выглядел доступным.
            $this->app['config']->offsetUnset($this->name());

            return $this;
        }

        return parent::boot();
    }

    public function packageBooted(): void
    {
        $this->loadModuleResources();
        $this->loadModuleRoutes();
    }

    protected function loadModuleResources(): void
    {
        $name = $this->name();

        $config = $this->modulePath("config/{$name}.php");
        if (is_file($config) && $this->app->runningInConsole()) {
            $this->publishes([$config => config_path("{$name}.php")], "{$name}-config");
        }

        // Миграции регистрируются в мигратор (применяются через `artisan migrate`), но не запускаются сами.
        $migrations = $this->modulePath('database/migrations');
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $lang = $this->modulePath('lang');
        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, $name);
            $this->loadJsonTranslationsFrom($lang);
        }

        $views = $this->modulePath('resources/views');
        if (is_dir($views)) {
            $this->loadViewsFrom($views, $name);
        }
    }

    protected function loadModuleRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $name = $this->name();
        $routes = $this->modulePath('routes');

        if (is_file("{$routes}/web.php")) {
            Route::middleware('web')->group("{$routes}/web.php");
        }
        if (is_file("{$routes}/admin.php")) {
            Route::middleware($this->adminMiddleware())
                ->prefix($this->adminRoutePrefix())
                ->name($this->adminRouteNamePrefix())
                ->group("{$routes}/admin.php");
        }
        if (is_file("{$routes}/api.php")) {
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix("api/{$name}")
                ->name("api.{$name}.")
                ->group("{$routes}/api.php");
        }
    }

    /** URL-префикс админ-роутов модуля (`routes/admin.php`). */
    protected function adminRoutePrefix(): string
    {
        return 'admin/'.$this->name();
    }

    /** Префикс имён админ-роутов модуля. */
    protected function adminRouteNamePrefix(): string
    {
        return 'admin.'.$this->name().'.';
    }

    /**
     * Middleware админ-роутов: вход, право `admin.access` и свежее подтверждение пароля.
     *
     * @return list<string>
     */
    protected function adminMiddleware(): array
    {
        return ['web', 'auth', 'admin', 'ladcms.password-confirm'];
    }
}
