<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('installs the site with a creator of the app user model', function () {
    $this->artisan('ladcms:install', [
        '--site-name' => 'My Community',
        '--language' => 'en',
        '--admin-login' => 'owner',
        '--admin-name' => 'Owner',
        '--admin-email' => 'owner@example.com',
        '--admin-password' => 'secret-password-123',
    ])->assertSuccessful();

    $admin = User::query()->where('login', 'owner')->sole();

    expect($admin->hasRole('creator'))->toBeTrue()
        ->and($admin->can('admin.access'))->toBeTrue()
        ->and(User::role('creator')->count())->toBe(1)
        ->and(DB::table('model_has_roles')->where('model_id', $admin->id)->value('model_type'))->toBe('user');
});
