<?php

namespace Ladcms\Core\Console;

use Illuminate\Console\Command;
use Ladcms\Core\Actions\Modules\UpdateInstalledModules;

class UpdateModulesCommand extends Command
{
    protected $signature = 'ladcms:update';

    protected $description = 'Re-run installation steps for every installed LADCMS module';

    public function handle(UpdateInstalledModules $update): int
    {
        $npmChanged = false;

        foreach ($update->handle() as $report) {
            $this->info("Module {$report->module} {$report->version} updated.");
            $npmChanged = $npmChanged || $report->npmChanged;
        }

        if ($npmChanged) {
            $this->warn('Run: npm install && npm run build');
        }

        return self::SUCCESS;
    }
}
