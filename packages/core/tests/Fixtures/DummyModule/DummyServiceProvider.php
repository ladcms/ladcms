<?php

declare(strict_types=1);

namespace Ladcms\Core\Tests\Fixtures\DummyModule;

use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasAdminPanel;
use Ladcms\Core\Module\Contracts\HasHeaderActions;
use Ladcms\Core\Module\Contracts\HasNavigation;
use Ladcms\Core\Module\Contracts\HasPermissions;
use Ladcms\Core\Module\Contracts\HasProfileActions;
use Ladcms\Core\Module\Contracts\HasProfileSections;
use Ladcms\Core\Module\Contracts\HasSettings;
use Ladcms\Core\Module\Contracts\HasWidgets;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Tests\Fixtures\DummyModule\Settings\DummySettings;

class DummyServiceProvider extends ModuleServiceProvider implements HasAdminPanel, HasHeaderActions, HasNavigation, HasPermissions, HasProfileActions, HasProfileSections, HasSettings, HasWidgets
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

    public function adminMenu(): array
    {
        return [['label' => 'dummy::messages.admin', 'icon' => 'box', 'route' => 'admin.dummy.index', 'order' => 10]];
    }

    public function settings(): array
    {
        return [DummySettings::class];
    }

    public function navigation(): array
    {
        return [
            ['key' => 'dummy', 'label' => 'Dummy', 'icon' => 'box', 'route' => 'dummy.index', 'order' => 25],
            ['key' => 'secret', 'label' => 'Secret', 'icon' => 'lock', 'route' => 'dummy.index', 'order' => 40, 'permission' => 'users.view'],
        ];
    }

    public function widgets(): array
    {
        return [
            ['name' => 'hello', 'component' => 'dummy::Hello', 'slot' => 'main', 'order' => 10, 'props' => fn (): array => ['greeting' => 'hi']],
            ['name' => 'missing', 'component' => 'dummy::Missing', 'slot' => 'main', 'order' => 20],
        ];
    }

    public function headerActions(): array
    {
        return [
            ['key' => 'dummy', 'icon' => 'box', 'component' => 'dummy::Action', 'order' => 50, 'auth' => false],
            ['key' => 'ghost', 'icon' => 'ghost', 'component' => 'dummy::MissingAction', 'order' => 60, 'auth' => false],
        ];
    }

    /** Счётчики вызовов для тестов ленивости профиля. */
    public static int $sectionsCalls = 0;

    public static int $countersCalls = 0;

    public function profileSections(): array
    {
        self::$sectionsCalls++;

        return [
            'tab' => ['label' => 'Dummy tab', 'component' => 'dummy::Tab'],
            'overview' => ['component' => 'dummy::Block', 'order' => 10],
            'counters' => function (User $target, ?User $viewer): array {
                self::$countersCalls++;

                return [['key' => 'dummy-posts', 'label' => 'Posts', 'value' => 7, 'tab' => 'dummy']];
            },
        ];
    }

    public function profileTabData(User $target, ?User $viewer): array
    {
        return ['marker' => 'dummy-tab-data', 'user_id' => $target->id];
    }

    public function profileActions(): array
    {
        return [
            ['label' => 'Wave', 'icon' => 'hand', 'component' => 'dummy::Wave', 'order' => 10],
            ['label' => 'Ghost', 'icon' => 'ghost', 'component' => 'dummy::MissingAction', 'order' => 20],
        ];
    }

    protected function moduleRoot(): string
    {
        return __DIR__;
    }
}
