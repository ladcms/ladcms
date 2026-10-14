<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'LADCMS');
        $this->migrator->add('general.site_description', '');
        $this->migrator->add('general.default_language', 'ru');
        $this->migrator->add('general.timezone', 'UTC');
        $this->migrator->add('general.home_page', 'home');
        $this->migrator->add('general.rules_markdown', '');

        $this->migrator->add('registration.open', true);
        $this->migrator->add('registration.require_email_verification', true);
        $this->migrator->add('registration.require_rules', false);
        $this->migrator->add('registration.invite_only', false);
        $this->migrator->add('registration.readonly_hours', 0);

        $this->migrator->add('moderation.violation_codes', [
            ['code' => 'spam', 'label' => 'Spam', 'min_days' => 1, 'max_days' => 30],
            ['code' => 'insult', 'label' => 'Insults', 'min_days' => 1, 'max_days' => 90],
            ['code' => 'rules', 'label' => 'Rules violation', 'min_days' => 1, 'max_days' => null],
        ]);
        $this->migrator->add('moderation.password_confirm_minutes', 30);

        $this->migrator->add('appearance.accent_color', '#0d9488');
        $this->migrator->add('appearance.logo_path');
        $this->migrator->add('appearance.default_theme', 'system');
    }
};
