<?php

namespace Ladcms\Core\Actions\Modules;

use Illuminate\Support\Facades\Artisan;
use Ladcms\Core\Models\Module;
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
        $module = $this->registry->get($name) ?? throw new ModuleNotFoundException($name);

        Artisan::call('migrate', ['--force' => true]);

        $createdPermissions = $this->permissions->handle($module);
        $addedNpm = $this->npm->handle($module->npmDependencies(), (string) config('core.package_json'));

        $module->install();

        Module::updateOrCreate(
            ['name' => $name],
            ['version' => $module->version(), 'installed_at' => now(), 'is_enabled' => true],
        );

        return new InstallReport($name, $module->version(), $createdPermissions, $addedNpm, $addedNpm !== []);
    }
}
