<?php

declare(strict_types=1);

namespace Ladcms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Ladcms\Core\Actions\Modules\UpdateInstalledModules;
use PDOException;

class UpdateModulesCommand extends Command
{
    protected $signature = 'ladcms:update';

    protected $description = 'Re-run installation steps for every installed LADCMS module';

    /**
     * Запускается из composer post-update-cmd, поэтому не должен ломать
     * `composer update` до миграций или без доступной БД.
     */
    public function handle(UpdateInstalledModules $update): int
    {
        try {
            if (! Schema::hasTable('modules')) {
                $this->warn('LADCMS is not installed yet (modules table missing); skipping module update.');

                return self::SUCCESS;
            }

            $reports = $update->handle();
        } catch (QueryException|PDOException $e) {
            $this->warn('Database unavailable; skipping module update: '.$e->getMessage());

            return self::SUCCESS;
        }

        $npmChanged = false;

        foreach ($reports as $report) {
            $this->info("Module {$report->module} {$report->version} updated.");
            $npmChanged = $npmChanged || $report->npmChanged;
        }

        if ($npmChanged) {
            $this->warn('Run: npm install && npm run build');
        }

        return self::SUCCESS;
    }
}
