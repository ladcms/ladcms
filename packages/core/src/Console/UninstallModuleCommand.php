<?php

namespace Ladcms\Core\Console;

use Illuminate\Console\Command;
use Ladcms\Core\Actions\Modules\UninstallModule;
use Ladcms\Core\Module\ModuleNotFoundException;

class UninstallModuleCommand extends Command
{
    protected $signature = 'ladcms:uninstall {module : Module name, e.g. forum}';

    protected $description = 'Uninstall a LADCMS module before `composer remove`';

    public function handle(UninstallModule $uninstall): int
    {
        $name = (string) $this->argument('module');
        $rollback = $this->confirm("Roll back migrations of [{$name}]? Data will be lost.", false);

        try {
            $uninstall->handle($name, $rollback);
        } catch (ModuleNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module {$name} uninstalled. Now run: composer remove ladcms/{$name}");

        return self::SUCCESS;
    }
}
