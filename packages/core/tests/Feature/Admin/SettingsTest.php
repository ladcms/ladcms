<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Actions\Moderation\BanUser;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;
use Ladcms\Core\Settings\ModerationSettings;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->moderator = User::factory()->create()->assignRole('moderator');
    $this->member = User::factory()->create()->assignRole('user');
});

function settingsAs(User $actor): mixed
{
    return test()->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from('/admin/settings/general');
}

function generalPayload(array $override = []): array
{
    return array_merge([
        'site_name' => 'New Name',
        'site_description' => 'Desc',
        'default_language' => 'ru',
        'timezone' => 'Europe/Moscow',
        'home_page' => 'home',
        'rules_markdown' => '# Rules',
    ], $override);
}

it('renders the edit page with schema, values and tabs', function () {
    settingsAs($this->admin)->get('/admin/settings/general')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('core::admin/settings/edit')
            ->where('group', 'general')
            ->has('groups', 4)
            ->where('groups.0.key', 'general')
            ->where('groups.0.href', url('/admin/settings/general'))
            ->where('schema.group', 'general')
            ->where('schema.fields.0.name', 'site_name')
            ->where('schema.fields.0.type', 'text')
            ->where('schema.fields.0.value', 'LADCMS'));
});

it('shows module settings groups as tabs and renders them', function () {
    $this->app->register(DummyServiceProvider::class);
    $this->artisan('migrate')->assertSuccessful();

    settingsAs($this->admin)->get('/admin/settings/dummy')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('group', 'dummy')
            ->has('groups', 5)
            ->where('groups.4.key', 'dummy')
            ->where('schema.fields.0.name', 'greeting')
            ->where('schema.fields.0.value', 'hello from settings'));
});

it('returns 404 for an unknown group', function () {
    settingsAs($this->admin)->get('/admin/settings/nope')->assertNotFound();
    settingsAs($this->admin)->put('/admin/settings/nope', [])->assertNotFound();
});

it('forbids users without settings.manage', function () {
    settingsAs($this->moderator)->get('/admin/settings/general')->assertForbidden();
    settingsAs($this->moderator)->put('/admin/settings/general', generalPayload())->assertForbidden();
    expect(app(GeneralSettings::class)->site_name)->toBe('LADCMS');
});

it('saves general settings', function () {
    settingsAs($this->admin)->put('/admin/settings/general', generalPayload())
        ->assertRedirect('/admin/settings/general')
        ->assertSessionHasNoErrors();

    $settings = app(GeneralSettings::class)->refresh();
    expect($settings->site_name)->toBe('New Name')
        ->and($settings->timezone)->toBe('Europe/Moscow');
});

it('rejects invalid values with validation errors', function () {
    settingsAs($this->admin)->put('/admin/settings/general', generalPayload(['default_language' => 'xx', 'site_name' => '']))
        ->assertSessionHasErrors(['default_language', 'site_name']);
    expect(app(GeneralSettings::class)->site_name)->toBe('LADCMS');
});

it('stores "0" as false for boolean fields', function () {
    expect(app(RegistrationSettings::class)->open)->toBeTrue();

    settingsAs($this->admin)->put('/admin/settings/registration', [
        'open' => '0',
        'require_email_verification' => '1',
        'require_rules' => '0',
        'invite_only' => '0',
        'readonly_hours' => '3',
    ])->assertSessionHasNoErrors();

    $settings = app(RegistrationSettings::class)->refresh();
    expect($settings->open)->toBeFalse()
        ->and($settings->require_email_verification)->toBeTrue()
        ->and($settings->readonly_hours)->toBe(3);
});

it('rejects violation codes without a code', function () {
    $response = settingsAs($this->admin)
        ->putJson('/admin/settings/moderation', [
            'violation_codes' => [['label' => 'No code', 'min_days' => 1, 'max_days' => null]],
            'password_confirm_minutes' => 15,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['violation_codes.0.code']);
});

it('rejects password_confirm_minutes below 1 with 422', function (int $minutes) {
    settingsAs($this->admin)->putJson('/admin/settings/moderation', ['password_confirm_minutes' => $minutes])
        ->assertStatus(422)->assertJsonValidationErrors(['password_confirm_minutes']);

    expect(app(ModerationSettings::class)->refresh()->password_confirm_minutes)->toBeGreaterThanOrEqual(1);
})->with([0, -1]);

it('rejects an empty violation codes list with 422', function () {
    settingsAs($this->admin)->putJson('/admin/settings/moderation', ['violation_codes' => [], 'password_confirm_minutes' => 15])
        ->assertStatus(422)->assertJsonValidationErrors(['violation_codes']);

    expect(app(ModerationSettings::class)->refresh()->violation_codes)->not->toBeEmpty();
});

it('saves violation codes and BanUser accepts the new code', function () {
    settingsAs($this->admin)->put('/admin/settings/moderation', [
        'violation_codes' => [
            ['code' => 'brand_new', 'label' => 'Brand new', 'min_days' => '1', 'max_days' => '30'],
            ['code' => 'forever', 'label' => 'Forever', 'min_days' => '0', 'max_days' => ''],
        ],
        'password_confirm_minutes' => '20',
    ])->assertSessionHasNoErrors();

    $settings = app(ModerationSettings::class)->refresh();
    expect($settings->violation_codes)->toBe([
        ['code' => 'brand_new', 'label' => 'Brand new', 'min_days' => 1, 'max_days' => 30],
        ['code' => 'forever', 'label' => 'Forever', 'min_days' => 0, 'max_days' => null],
    ])->and($settings->password_confirm_minutes)->toBe(20);

    $ban = app(BanUser::class)->handle($this->admin, $this->member, BanType::Full, 'brand_new', now()->addDays(5));
    expect($ban->reason_code)->toBe('brand_new');
});

it('audits changes and truncates long values', function () {
    $long = str_repeat('x', 300);

    settingsAs($this->admin)->put('/admin/settings/general', generalPayload(['rules_markdown' => $long]))
        ->assertSessionHasNoErrors();

    $activity = Activity::query()->where('event', 'settings.updated')->sole();
    expect($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->properties['group'])->toBe('general')
        ->and($activity->properties['changed']['site_name'])->toBe(['LADCMS', 'New Name'])
        ->and($activity->properties['changed']['rules_markdown'][1])->toBe('[…]');
});

it('writes no audit row when nothing changed', function () {
    $current = app(GeneralSettings::class);
    settingsAs($this->admin)->put('/admin/settings/general', [
        'site_name' => $current->site_name,
        'site_description' => $current->site_description,
        'default_language' => $current->default_language,
        'timezone' => $current->timezone,
        'home_page' => $current->home_page,
        'rules_markdown' => $current->rules_markdown,
    ])->assertSessionHasNoErrors();

    expect(Activity::query()->where('event', 'settings.updated')->count())->toBe(0);
});
