<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Settings\AppearanceSettings;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\ModerationSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Settings\Schema\FieldType;
use Ladcms\Core\Settings\Schema\SettingsSchema;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\Settings\DummySettings;

uses(RefreshDatabase::class);

function schemaField(array $schema, string $name): array
{
    return collect($schema['fields'])->firstWhere('name', $name);
}

it('describes general settings with inferred and overridden types', function () {
    $schema = SettingsSchema::for(GeneralSettings::class);

    expect($schema['group'])->toBe('general')
        ->and($schema['label'])->toBe(__('core::settings.general.title'))
        ->and(collect($schema['fields'])->pluck('name')->all())->toBe(
            ['site_name', 'site_description', 'default_language', 'timezone', 'home_page', 'rules_markdown'],
        )
        ->and(schemaField($schema, 'site_name')['type'])->toBe(FieldType::Text)
        ->and(schemaField($schema, 'site_name')['value'])->toBe('LADCMS')
        ->and(schemaField($schema, 'site_description')['type'])->toBe(FieldType::Textarea)
        ->and(schemaField($schema, 'default_language')['type'])->toBe(FieldType::Select)
        ->and(schemaField($schema, 'timezone')['type'])->toBe(FieldType::Select)
        ->and(schemaField($schema, 'rules_markdown')['type'])->toBe(FieldType::Markdown);
});

it('infers boolean and number types for registration settings', function () {
    $schema = SettingsSchema::for(RegistrationSettings::class);

    expect(schemaField($schema, 'open')['type'])->toBe(FieldType::Boolean)
        ->and(schemaField($schema, 'invite_only')['type'])->toBe(FieldType::Boolean)
        ->and(schemaField($schema, 'readonly_hours')['type'])->toBe(FieldType::Number);
});

it('marks violation codes and password confirmation in moderation settings', function () {
    $schema = SettingsSchema::for(ModerationSettings::class);

    expect(schemaField($schema, 'violation_codes')['type'])->toBe(FieldType::ViolationCodes)
        ->and(schemaField($schema, 'violation_codes')['value'])->toBeArray()->not->toBeEmpty()
        ->and(schemaField($schema, 'password_confirm_minutes')['type'])->toBe(FieldType::Number);
});

it('renders colour, theme select and nullable text for appearance settings', function () {
    $schema = SettingsSchema::for(AppearanceSettings::class);

    expect(schemaField($schema, 'accent_color')['type'])->toBe(FieldType::Color)
        ->and(schemaField($schema, 'default_theme')['type'])->toBe(FieldType::Select)
        ->and(collect(schemaField($schema, 'default_theme')['options'])->pluck('value')->all())->toBe(['system', 'light', 'dark'])
        ->and(schemaField($schema, 'logo_path')['type'])->toBe(FieldType::Text)
        ->and(schemaField($schema, 'logo_path')['nullable'])->toBeTrue();
});

it('provides locale and timezone options', function () {
    config(['core.locales' => ['ru' => 'Русский', 'en' => 'English']]);
    $schema = SettingsSchema::for(GeneralSettings::class);

    expect(schemaField($schema, 'default_language')['options'])->toBe([
        ['value' => 'ru', 'label' => 'Русский'],
        ['value' => 'en', 'label' => 'English'],
    ])->and(collect(schemaField($schema, 'timezone')['options'])->pluck('value'))->toContain('UTC', 'Europe/Moscow');
});

it('takes labels and descriptions from lang files', function () {
    app()->setLocale('en');
    $schema = SettingsSchema::for(GeneralSettings::class);

    expect(schemaField($schema, 'site_name')['label'])->toBe(__('core::settings.general.fields.site_name.label'))
        ->and(schemaField($schema, 'site_name')['label'])->not->toContain('settings.')
        ->and(schemaField($schema, 'site_name')['description'])->toBe(__('core::settings.general.fields.site_name.description'));
});

it('falls back to humanized names for modules without lang entries', function () {
    $this->app->register(DummyServiceProvider::class);
    $this->artisan('migrate')->assertSuccessful();

    $schema = SettingsSchema::for(DummySettings::class);

    expect($schema['group'])->toBe('dummy')
        ->and($schema['label'])->toBe('Dummy')
        ->and(schemaField($schema, 'greeting')['label'])->toBe('Greeting')
        ->and(schemaField($schema, 'greeting')['description'])->toBeNull()
        ->and(schemaField($schema, 'greeting')['value'])->toBe('hello from settings');
});

it('exposes numeric and list bounds in the schema', function () {
    $moderation = SettingsSchema::for(ModerationSettings::class);
    $registration = SettingsSchema::for(RegistrationSettings::class);
    $general = SettingsSchema::for(GeneralSettings::class);

    expect(schemaField($moderation, 'password_confirm_minutes'))->toMatchArray(['min' => 1, 'max' => 1440])
        ->and(schemaField($moderation, 'violation_codes'))->toMatchArray(['min' => 1, 'max' => null])
        ->and(schemaField($registration, 'readonly_hours'))->toMatchArray(['min' => 0, 'max' => null])
        ->and(schemaField($general, 'site_name'))->toMatchArray(['min' => null, 'max' => null]);
});
