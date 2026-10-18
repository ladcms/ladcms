<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui;

use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasHeaderActions;
use Ladcms\Core\Module\ModuleRegistry;

/** Иконки-действия шапки публичного layout: ядро и модули с HasHeaderActions; `auth` скрывает действие от гостей. */
class HeaderActions
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<array{key: string, icon: string, component: string, order: int}> */
    public function items(?User $user): array
    {
        $items = [];

        foreach ($this->modules->implementing(HasHeaderActions::class) as $module) {
            foreach ($module->headerActions() as $action) {
                if (($action['auth'] ?? false) && ! $user instanceof User) {
                    continue;
                }

                $items[] = [
                    'key' => $action['key'],
                    'icon' => $action['icon'],
                    'component' => $action['component'],
                    'order' => $action['order'],
                ];
            }
        }

        usort($items, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $items;
    }
}
