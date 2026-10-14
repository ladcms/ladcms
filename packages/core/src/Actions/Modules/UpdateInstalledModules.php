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

        /** @var string $name */
        foreach (Module::enabled()->pluck('name') as $name) {
            if ($name === 'core' || ! $this->registry->has($name)) {
                continue;
            }
            $reports[] = $this->install->handle($name);
        }

        return $reports;
    }
}
