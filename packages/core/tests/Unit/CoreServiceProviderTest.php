<?php

declare(strict_types=1);

use Ladcms\Core\CoreServiceProvider;

it('publishes the core config', function () {
    expect(config('core.version'))->toBe('0.1.0');
});

it('declares the npm dependencies of the core UI kit', function () {
    $deps = new CoreServiceProvider(app())->npmDependencies();

    expect($deps)->toHaveKey('cmdk')
        ->and($deps)->toHaveKeys(['@radix-ui/react-alert-dialog', '@radix-ui/react-popover', '@radix-ui/react-switch', '@radix-ui/react-tabs']);
});
