<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ladcms\Core\Admin\ModulesOverview;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Tests\Fixtures\BrokenModule\BrokenServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\DummyServiceProvider;

uses(RefreshDatabase::class);

it('builds rows sorted core first then by name', function () {
    $this->app->register(DummyServiceProvider::class);
    $this->app->register(BrokenServiceProvider::class);
    Module::create(['name' => 'dummy', 'version' => '1.0.0', 'installed_at' => now(), 'is_enabled' => true]);
    Module::create(['name' => 'ghost', 'version' => '2.0.0', 'installed_at' => now(), 'is_enabled' => true]);

    $rows = collect(app(ModulesOverview::class)->rows());

    expect($rows->firstWhere('name', 'core'))->toMatchArray(['is_enabled' => true, 'installed_version' => null, 'needs_update' => false, 'reason' => null])
        ->and($rows->pluck('name')->all())->toBe(['core', 'broken', 'dummy', 'ghost'])
        ->and($rows->firstWhere('name', 'core')['status'])->toBe('active') // core is never not_installed, even without a row
        ->and($rows->firstWhere('name', 'dummy'))->toMatchArray(['status' => 'active', 'needs_update' => false, 'dependencies' => []])
        ->and($rows->firstWhere('name', 'broken')['status'])->toBe('failed')
        ->and($rows->firstWhere('name', 'ghost')['version'])->toBeNull();
});
