<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui;

use Closure;
use Illuminate\Support\Arr;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasWidgets;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Ui\Concerns\UiRegistry;
use Throwable;

/**
 * Виджеты слота публичного layout. Props считаются лениво, только для запрошенного слота;
 * виджеты, чьего компонента нет на диске, отбрасываются (с предупреждением в лог — один раз на компонент).
 */
class Widgets
{
    use UiRegistry;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @param  'sidebar'|'main'  $slot
     * @return list<array{name: string, component: string, module: string, order: int, props: array<string, mixed>}>
     */
    public function for(string $slot, ?User $user): array
    {
        $widgets = [];

        foreach ($this->modules->implementing(HasWidgets::class) as $moduleName => $module) {
            try {
                $entries = $module->widgets();
            } catch (Throwable $e) {
                $this->warnOnce("module:{$moduleName}", "Module [{$moduleName}] widgets() failed: {$e->getMessage()}");

                continue;
            }

            foreach ($entries as $widget) {
                try {
                    $this->requireFields($widget, ['name', 'component', 'slot', 'order']);
                    if ($widget['slot'] !== $slot) {
                        continue;
                    }

                    $component = $widget['component'];
                    if (! $this->componentExists($this->modules, $component, 'widgets')) {
                        continue;
                    }

                    $props = $widget['props'] ?? [];

                    $widgets[] = [
                        'name' => $widget['name'],
                        'component' => $component,
                        'module' => $module->name(),
                        'order' => $widget['order'],
                        'props' => $props instanceof Closure ? $props($user) : $props,
                    ];
                } catch (Throwable $e) {
                    $this->warnOnce("entry:{$moduleName}:".Arr::get($widget, 'name', '?'), "Module [{$moduleName}] widget skipped: {$e->getMessage()}");
                }
            }
        }

        /** @var list<array{name: string, component: string, module: string, order: int, props: array<string, mixed>}> */
        return $this->sortItems($widgets, 'name');
    }
}
