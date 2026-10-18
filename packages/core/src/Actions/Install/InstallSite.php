<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Install;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\CoreServiceProvider;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Support\AdministratorRules;
use Ladcms\Core\Support\LoginRules;

final readonly class InstallSite
{
    public function __construct(private CreateAdministrator $createAdministrator) {}

    /** @throws ValidationException */
    public function handle(SiteInstallData $data, bool $force = false): User
    {
        Artisan::call('migrate', ['--force' => true]);

        if (! $force && Module::where('name', 'core')->exists()) {
            throw new SiteAlreadyInstalledException;
        }

        // Данные администратора проверяются до сидеров и настроек: ошибка не оставляет полуустановленный сайт.
        AdministratorRules::validate(LoginRules::normalize($data->adminLogin), $data->adminName, $data->adminEmail, $data->adminPassword);

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
