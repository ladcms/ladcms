<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Modules;

use Ladcms\Core\Models\Module;
use Ladcms\Core\Module\ModuleRegistry;

class UpdateInstalledModules
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly InstallModule $install,
    ) {}

    /** @return list<InstallReport> */
    public function handle(): array
    {
        $reports = [];

        // Ядро — первым (его миграции и права нужны модулям) и всегда, если сайт установлен.
        $names = Module::enabled()->where('name', '!=', 'core')->orderBy('name')->pluck('name')->all();
        if (Module::query()->where('name', 'core')->exists()) {
            array_unshift($names, 'core');
        }

        /** @var string $name */
        foreach ($names as $name) {
            if (! $this->registry->has($name)) {
                continue;
            }
            $reports[] = $this->install->handle($name);
        }

        return $reports;
    }
}
