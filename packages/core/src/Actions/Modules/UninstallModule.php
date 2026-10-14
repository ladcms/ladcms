<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\ModuleHasDependentsException;
use Ladcms\Core\Module\ModuleNotFoundException;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UninstallModule
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function handle(string $name, bool $rollbackMigrations = false): void
    {
        $module = $this->ensureCanUninstall($name);

        $module->uninstall();

        if ($rollbackMigrations) {
            $this->rollbackMigrations($module);
        }

        if ($module instanceof HasPermissions) {
            Permission::whereIn('name', array_keys($module->permissions()))
                ->where('guard_name', 'web')
                ->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Module::where('name', $name)->delete();
    }

    /**
     * Проверки до любых изменений; команда вызывает их до вопроса об откате миграций.
     *
     * @throws InvalidArgumentException для core
     * @throws ModuleNotFoundException
     * @throws ModuleHasDependentsException
     */
    public function ensureCanUninstall(string $name): ModuleServiceProvider
    {
        if ($name === 'core') {
            throw new InvalidArgumentException('The core module cannot be uninstalled.');
        }

        // Модуль, не прошедший проверку, тоже можно удалить — поэтому провайдер берём мимо валидации.
        $module = $this->registry->getRegistered($name) ?? throw new ModuleNotFoundException($name);

        $dependents = $this->registry->dependents($name);
        if ($dependents !== []) {
            throw new ModuleHasDependentsException($name, $dependents);
        }

        return $module;
    }

    /** Откатывает только миграции модуля, записанные в таблице migrations, в обратном порядке. */
    private function rollbackMigrations(ModuleServiceProvider $module): void
    {
        $files = glob($module->modulePath('database/migrations').'/*.php') ?: [];
        rsort($files);

        foreach ($files as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            $query = DB::table('migrations')->where('migration', $migrationName);

            if (! $query->exists()) {
                continue;
            }

            $migration = require $file;
            if ($migration instanceof Migration && method_exists($migration, 'down')) {
                $migration->down();
            }

            $query->delete();
        }
    }
}
