<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\BadConstraintModule;

use Ladcms\Core\Module\ModuleServiceProvider;

class BadConstraintServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'bad-constraint';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function coreVersion(): string
    {
        return '1.x-foo';
    }

    protected function moduleRoot(): string
    {
        return __DIR__;
    }
}
