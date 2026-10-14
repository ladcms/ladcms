<?php

declare(strict_types=1);

namespace Ladcms\Core\Console;

use Illuminate\Console\Command;
use Ladcms\Core\Actions\Modules\InstallModule;
use Ladcms\Core\Module\ModuleFailedException;
use Ladcms\Core\Module\ModuleNotFoundException;

class InstallModuleCommand extends Command
{
    protected $signature = 'ladcms:install {module : Module name, e.g. forum}';

    protected $description = 'Install or update a LADCMS module (migrations, permissions, npm dependencies)';

    public function handle(InstallModule $install): int
    {
        try {
            $report = $install->handle((string) $this->argument('module'));
        } catch (ModuleNotFoundException|ModuleFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module {$report->module} {$report->version} installed.");

        if ($report->createdPermissions !== []) {
            $this->line('Permissions created: '.implode(', ', $report->createdPermissions));
        }
        if ($report->npmChanged) {
            $this->line('npm dependencies added: '.implode(', ', array_keys($report->addedNpmDependencies)));
            $this->warn('Run: npm install && npm run build');
        }

        return self::SUCCESS;
    }
}
