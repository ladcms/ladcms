<?php

use Ladcms\Core\Module\ModuleRegistry;

it('registers core as a module', function () {
    expect(app(ModuleRegistry::class)->names())->toContain('core');
});

it('exposes module lifecycle commands', function () {
    $this->artisan('list')->expectsOutputToContain('ladcms:install')->expectsOutputToContain('ladcms:update');
});
