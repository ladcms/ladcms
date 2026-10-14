<?php

declare(strict_types=1);

use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Tests\Support\ReversedProviderOrder;

uses(ReversedProviderOrder::class);

it('boots modules regardless of provider registration order', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->has('core'))->toBeTrue()
        ->and($registry->has('needs-friend'))->toBeTrue()
        ->and($registry->failures())->toBe([])
        ->and(config('dummy.greeting'))->toBe('hello');

    $this->get('/dummy')->assertOk()->assertSee('dummy web');
});
