<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\FriendModule;

use Ladcms\Core\Module\ModuleServiceProvider;

class FriendServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'friend';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function coreVersion(): string
    {
        return '^0.1';
    }

    protected function moduleRoot(): string
    {
        return __DIR__;
    }
}
