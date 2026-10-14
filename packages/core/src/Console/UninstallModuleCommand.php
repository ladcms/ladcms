<?php

declare(strict_types=1);

namespace Ladcms\Core\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Ladcms\Core\Actions\Modules\UninstallModule;
use Ladcms\Core\Module\ModuleHasDependentsException;
use Ladcms\Core\Module\ModuleNotFoundException;

class UninstallModuleCommand extends Command
{
    protected $signature = 'ladcms:uninstall {module : Module name, e.g. forum}';

    protected $description = 'Uninstall a LADCMS module before `composer remove`';

    public function handle(UninstallModule $uninstall): int
    {
        $name = (string) $this->argument('module');

        try {
            // Проверки (существует, не core, нет зависимых) — до вопроса об откате миграций.
            $uninstall->ensureCanUninstall($name);
            $rollback = $this->confirm("Roll back migrations of [{$name}]? Data will be lost.", false);
            $uninstall->handle($name, $rollback);
        } catch (ModuleNotFoundException|ModuleHasDependentsException|InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module {$name} uninstalled. Now run: composer remove ladcms/{$name}");

        return self::SUCCESS;
    }
}
