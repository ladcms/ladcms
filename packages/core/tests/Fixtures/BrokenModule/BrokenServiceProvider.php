<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\BrokenModule;

use Ladcms\Core\Module\ModuleServiceProvider;

class BrokenServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'broken';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function coreVersion(): string
    {
        return '^9.0';
    }

    protected function moduleRoot(): string
    {
        return __DIR__;
    }
}
