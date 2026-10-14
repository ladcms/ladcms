<?php

declare(strict_types=1);

namespace Ladcms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Install\InstallSite;
use Ladcms\Core\Actions\Install\SiteAlreadyInstalledException;
use Ladcms\Core\Actions\Install\SiteInstallData;
use Ladcms\Core\Actions\Modules\InstallModule;
use Ladcms\Core\Module\ModuleFailedException;
use Ladcms\Core\Module\ModuleNotFoundException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallModuleCommand extends Command
{
    protected $signature = 'ladcms:install
        {module? : Module name, e.g. forum; omit to install LADCMS itself}
        {--site-name= : Site name}
        {--language=ru : Default site language}
        {--admin-login= : Administrator login}
        {--admin-name= : Administrator display name}
        {--admin-email= : Administrator e-mail}
        {--admin-password= : Administrator password (min. 12 characters)}
        {--force : Re-run seeders on an already installed site}';

    protected $description = 'Install LADCMS (no argument) or a module (with name)';

    public function handle(): int
    {
        $module = $this->argument('module');

        if (is_string($module) && $module !== '') {
            return $this->installModule(app(InstallModule::class), $module);
        }

        return $this->installSite(app(InstallSite::class));
    }

    private function installModule(InstallModule $install, string $module): int
    {
        try {
            $report = $install->handle($module);
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

    private function installSite(InstallSite $install): int
    {
        $values = [];
        $missing = [];

        foreach ($this->siteFields() as $option => [$label, $secret]) {
            $value = $this->option($option);

            if (is_string($value) && $value !== '') {
                $values[$option] = $value;
            } elseif ($this->input->isInteractive()) {
                $values[$option] = $secret
                    ? password(label: $label, required: true)
                    : text(label: $label, required: true);
            } else {
                $missing[] = '--'.$option;
            }
        }

        if ($missing !== []) {
            $this->error('Missing required options in non-interactive mode: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $data = new SiteInstallData(
            siteName: $values['site-name'],
            language: $values['language'],
            adminLogin: $values['admin-login'],
            adminName: $values['admin-name'],
            adminEmail: $values['admin-email'],
            adminPassword: $values['admin-password'],
        );

        try {
            $admin = $install->handle($data, (bool) $this->option('force'));
        } catch (SiteAlreadyInstalledException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->info("LADCMS installed. Log in as {$admin->login}.");

        return self::SUCCESS;
    }

    /** @return array<string, array{0: string, 1: bool}> */
    private function siteFields(): array
    {
        return [
            'site-name' => ['Site name', false],
            'language' => ['Default language', false],
            'admin-login' => ['Administrator login', false],
            'admin-name' => ['Administrator name', false],
            'admin-email' => ['Administrator e-mail', false],
            'admin-password' => ['Administrator password (min. 12 characters)', true],
        ];
    }
}
