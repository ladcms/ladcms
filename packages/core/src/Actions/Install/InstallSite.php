<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Install;

use Illuminate\Support\Facades\Artisan;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;

final readonly class InstallSite
{
    public function __construct(private CreateAdministrator $createAdministrator) {}

    public function handle(SiteInstallData $data, bool $force = false): User
    {
        Artisan::call('migrate', ['--force' => true]);

        if (! $force && Module::where('name', 'core')->exists()) {
            throw new SiteAlreadyInstalledException;
        }

        (new RolesSeeder)->run();
        (new PermissionsSeeder)->run();

        $general = app(GeneralSettings::class);
        $general->site_name = $data->siteName;
        $general->default_language = $data->language;
        $general->save();

        $admin = $this->createAdministrator->handle($data->adminLogin, $data->adminName, $data->adminEmail, $data->adminPassword);

        Module::firstOrCreate(['name' => 'core'], ['version' => CoreServiceProvider::VERSION, 'installed_at' => now(), 'is_enabled' => true]);

        return $admin;
    }
}
