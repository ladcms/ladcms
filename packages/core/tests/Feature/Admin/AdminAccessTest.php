<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Ladcms\Core\Database\Seeders\PermissionsSeeder;
use Ladcms\Core\Database\Seeders\RolesSeeder;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\ModerationSettings;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    // Fortify-роуты в Testbench отсутствуют — заглушки.
    Route::get('/login', fn () => 'login')->name('login');
    Route::get('/user/confirm-password', fn () => 'confirm')->name('password.confirm');
});

it('redirects guests to login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('forbids users without admin.access', function () {
    $user = User::factory()->create()->assignRole('user');

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('requires a confirmed password', function () {
    $mod = User::factory()->create()->assignRole('moderator');

    $this->actingAs($mod)->get('/admin')->assertRedirect('/user/confirm-password');
});

it('renders the dashboard with the admin menu after confirmation', function () {
    $mod = User::factory()->create()->assignRole('moderator');

    $this->actingAs($mod)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($mod) {
            $page->component('core::admin/dashboard')
                ->has('stats.users_total')
                ->has('admin.menu')
                ->where('admin.user.id', $mod->id);

            $keys = collect($page->toArray()['props']['admin']['menu'])->pluck('key');
            expect($keys)->toContain('dashboard', 'users', 'moderation')
                ->and($keys)->not->toContain('roles', 'settings', 'modules');
        });
});

it('does not share the admin menu outside admin routes', function () {
    $mod = User::factory()->create()->assignRole('moderator');
    Route::middleware('web')->get('/inertia-probe', fn () => inertia('core::home'));

    $this->actingAs($mod)->get('/inertia-probe')->assertInertia(fn (AssertableInertia $page) => $page->where('admin', null));
});

it('uses the password confirmation TTL from moderation settings', function () {
    $settings = app(ModerationSettings::class);
    $settings->password_confirm_minutes = 1;
    $settings->save();
    $mod = User::factory()->create()->assignRole('moderator');

    $this->actingAs($mod)
        ->withSession(['auth.password_confirmed_at' => time() - 120])
        ->get('/admin')
        ->assertRedirect('/user/confirm-password');

    $this->actingAs($mod)
        ->withSession(['auth.password_confirmed_at' => time() - 30])
        ->get('/admin')
        ->assertOk();
});
