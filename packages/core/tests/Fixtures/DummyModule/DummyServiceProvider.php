<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\DummyModule;

use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\ModuleServiceProvider;

class DummyServiceProvider extends ModuleServiceProvider implements HasPermissions
{
    public function name(): string
    {
        return 'dummy';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function coreVersion(): string
    {
        return '^0.1';
    }

    public function npmDependencies(): array
    {
        return ['left-pad' => '^1.3'];
    }

    public function permissions(): array
    {
        return ['dummy.view' => 'dummy::messages.perm_view'];
    }

    protected function moduleRoot(): string
    {
        return __DIR__;
    }
}
