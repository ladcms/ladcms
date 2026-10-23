<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui;

use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasHeaderActions;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Ui\Concerns\UiRegistry;
use Throwable;

/**
 * Иконки-действия шапки публичного layout: ядро и модули с HasHeaderActions; `auth` скрывает действие от гостей.
 * Сломанный элемент или компонент без файла пропускается с предупреждением, а не роняет страницу.
 */
class HeaderActions
{
    use UiRegistry;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<array{key: string, icon: string, component: string, order: int}> */
    public function items(?User $user): array
    {
        $items = [];

        foreach ($this->modules->implementing(HasHeaderActions::class) as $name => $module) {
            try {
                $actions = $module->headerActions();
            } catch (Throwable $e) {
                $this->warnOnce("module:{$name}", "Module [{$name}] headerActions() failed: {$e->getMessage()}");

                continue;
            }

            foreach ($actions as $action) {
                try {
                    $this->requireFields($action, ['key', 'icon', 'component', 'order']);

                    if (($action['auth'] ?? false) && ! $user instanceof User) {
                        continue;
                    }

                    $item = [
                        'key' => $action['key'],
                        'icon' => $action['icon'],
                        'component' => $action['component'],
                        'order' => $action['order'],
                    ];

                    // Внутри try: нестроковый component — TypeError, а не 500 на всей странице.
                    if ($this->componentExists($this->modules, $item['component'], 'header-actions')) {
                        $items[] = $item;
                    }
                } catch (Throwable $e) {
                    $this->warnOnce("entry:{$name}", "Module [{$name}] has a malformed header action: {$e->getMessage()}");
                }
            }
        }

        /** @var list<array{key: string, icon: string, component: string, order: int}> */
        return $this->sortItems($items, 'key');
    }
}
