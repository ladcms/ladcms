<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui;

use Closure;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasWidgets;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;

/**
 * Виджеты слота публичного layout. Props считаются лениво, только для запрошенного слота;
 * виджеты, чьего компонента нет на диске, отбрасываются (с предупреждением в лог — один раз на компонент).
 */
class Widgets
{
    /** @var array<string, true> */
    private array $warned = [];

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @param  'sidebar'|'main'  $slot
     * @return list<array{name: string, component: string, module: string, order: int, props: array<string, mixed>}>
     */
    public function for(string $slot, ?User $user): array
    {
        $widgets = [];

        foreach ($this->modules->implementing(HasWidgets::class) as $module) {
            foreach ($module->widgets() as $widget) {
                if ($widget['slot'] !== $slot || ! $this->exists($widget['component'])) {
                    continue;
                }

                $props = $widget['props'] ?? [];

                $widgets[] = [
                    'name' => $widget['name'],
                    'component' => $widget['component'],
                    'module' => $module->name(),
                    'order' => $widget['order'],
                    'props' => $props instanceof Closure ? $props($user) : $props,
                ];
            }
        }

        usort($widgets, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $widgets;
    }

    private function exists(string $component): bool
    {
        [$name, $file] = array_pad(explode('::', $component, 2), 2, '');
        $module = $this->modules->get($name);

        if ($module instanceof ModuleServiceProvider
            && $file !== ''
            && ! str_contains($file, '/')
            && is_file($module->modulePath("resources/js/widgets/{$file}.tsx"))) {
            return true;
        }

        if (! isset($this->warned[$component])) {
            $this->warned[$component] = true;
            logger()->warning("Widget component [{$component}] has no file under resources/js/widgets/; widget skipped.");
        }

        return false;
    }
}
