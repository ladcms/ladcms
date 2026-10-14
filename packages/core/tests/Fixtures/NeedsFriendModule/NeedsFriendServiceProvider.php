<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\NeedsFriendModule;

use Ladcms\Core\Module\ModuleServiceProvider;

class NeedsFriendServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'needs-friend';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function coreVersion(): string
    {
        return '^0.1';
    }

    public function dependencies(): array
    {
        return ['friend'];
    }

    protected function moduleRoot(): string
    {
        return __DIR__;
    }
}
