<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Models\Module;

uses(RefreshDatabase::class);

it('stores installed modules', function () {
    Module::create(['name' => 'dummy', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);
    Module::create(['name' => 'off', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => false]);

    expect(Module::enabled()->pluck('name')->all())->toBe(['dummy']);
});
