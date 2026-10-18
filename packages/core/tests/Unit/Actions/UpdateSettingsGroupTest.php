<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Actions\Settings\UpdateSettingsGroup;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\AppearanceSettings;
use Ladcms\Core\Settings\ModerationSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
});

it('requires settings.manage', function () {
    $user = User::factory()->create()->assignRole('moderator');

    app(UpdateSettingsGroup::class)->handle($user, RegistrationSettings::class, []);
})->throws(AuthorizationException::class);

it('casts values to PHP types', function () {
    $settings = app(UpdateSettingsGroup::class)->handle($this->admin, RegistrationSettings::class, [
        'open' => false, 'require_email_verification' => true, 'require_rules' => '1', 'invite_only' => 0, 'readonly_hours' => '5',
    ]);

    expect($settings->open)->toBeFalse()
        ->and($settings->require_email_verification)->toBeTrue()
        ->and($settings->require_rules)->toBeTrue()
        ->and($settings->invite_only)->toBeFalse()
        ->and($settings->readonly_hours)->toBe(5);
});

it('validates colour and nullable text', function () {
    expect(fn () => app(UpdateSettingsGroup::class)->handle($this->admin, AppearanceSettings::class, [
        'accent_color' => 'red', 'logo_path' => null, 'default_theme' => 'dark',
    ]))->toThrow(ValidationException::class);

    $settings = app(UpdateSettingsGroup::class)->handle($this->admin, AppearanceSettings::class, [
        'accent_color' => '#AABBCC', 'logo_path' => '', 'default_theme' => 'dark',
    ]);
    expect($settings->accent_color)->toBe('#AABBCC')->and($settings->logo_path)->toBeNull();
});

it('rejects duplicate codes and max_days below min_days', function () {
    $input = fn (array $codes) => ['violation_codes' => $codes, 'password_confirm_minutes' => 10];
    $row = fn (string $code, int $min = 0, ?int $max = null) => ['code' => $code, 'label' => 'L', 'min_days' => $min, 'max_days' => $max];

    expect(fn () => app(UpdateSettingsGroup::class)->handle($this->admin, ModerationSettings::class, $input([$row('a'), $row('a')])))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateSettingsGroup::class)->handle($this->admin, ModerationSettings::class, $input([$row('a', 5, 2)])))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateSettingsGroup::class)->handle($this->admin, ModerationSettings::class, $input([$row('Bad Code')])))
        ->toThrow(ValidationException::class);
});

it('does not write an audit row when nothing changed', function () {
    $current = app(RegistrationSettings::class);
    app(UpdateSettingsGroup::class)->handle($this->admin, RegistrationSettings::class, [
        'open' => $current->open, 'require_email_verification' => $current->require_email_verification,
        'require_rules' => $current->require_rules, 'invite_only' => $current->invite_only, 'readonly_hours' => $current->readonly_hours,
    ]);

    expect(Activity::query()->count())->toBe(0);
});

it('rejects password_confirm_minutes below 1 or above 1440', function (int $minutes) {
    app(UpdateSettingsGroup::class)->handle($this->admin, ModerationSettings::class, ['password_confirm_minutes' => $minutes]);
})->with([0, -1, 1441])->throws(ValidationException::class);

it('accepts password_confirm_minutes at the bounds', function () {
    $settings = app(UpdateSettingsGroup::class)->handle($this->admin, ModerationSettings::class, ['password_confirm_minutes' => 1]);
    expect($settings->password_confirm_minutes)->toBe(1);

    $settings = app(UpdateSettingsGroup::class)->handle($this->admin, ModerationSettings::class, ['password_confirm_minutes' => 1440]);
    expect($settings->password_confirm_minutes)->toBe(1440);
});

it('rejects an empty violation codes list', function () {
    app(UpdateSettingsGroup::class)->handle($this->admin, ModerationSettings::class, ['violation_codes' => []]);
})->throws(ValidationException::class);

it('rejects negative readonly_hours but accepts zero', function () {
    expect(fn () => app(UpdateSettingsGroup::class)->handle($this->admin, RegistrationSettings::class, ['readonly_hours' => -1]))
        ->toThrow(ValidationException::class);

    expect(app(UpdateSettingsGroup::class)->handle($this->admin, RegistrationSettings::class, ['readonly_hours' => 0])->readonly_hours)->toBe(0);
});
