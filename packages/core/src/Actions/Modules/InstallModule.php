<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

use Illuminate\Support\Facades\Artisan;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Module\ModuleFailedException;
use Ladcms\Core\Module\ModuleNotFoundException;
use Ladcms\Core\Module\ModuleRegistry;

class InstallModule
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly SyncModulePermissions $permissions,
        private readonly SyncNpmDependencies $npm,
    ) {}

    public function handle(string $name): InstallReport
    {
        if (! $this->registry->isRegistered($name)) {
            throw new ModuleNotFoundException($name);
        }

        $module = $this->registry->get($name)
            ?? throw new ModuleFailedException($name, $this->registry->failures()[$name]->reason ?? 'unknown reason');

        Artisan::call('migrate', ['--force' => true]);

        $createdPermissions = $this->permissions->handle($module);
        $addedNpm = $this->npm->handle($module->npmDependencies(), (string) config('core.package_json'));

        $module->install();

        // Повторная установка (обновление) не сбрасывает дату установки и не включает выключенный модуль.
        $record = Module::firstOrNew(['name' => $name]);
        if (! $record->exists) {
            $record->installed_at = now();
            $record->is_enabled = true;
        }
        $record->version = $module->version();
        $record->save();

        return new InstallReport($name, $module->version(), $createdPermissions, $addedNpm, $addedNpm !== []);
    }
}
