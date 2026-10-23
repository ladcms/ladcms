<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui;

use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasProfileActions;
use Ladcms\Core\Module\Contracts\HasProfileSections;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Ui\Concerns\UiRegistry;
use Throwable;

/**
 * Данные публичного профиля от модулей: вкладки, блоки «Обзора», счётчики шапки, кнопки действий.
 * Ключ вкладки/блока — имя модуля. Сломанный модуль или компонент без файла пропускается с предупреждением.
 */
class ProfileSections
{
    use UiRegistry;

    public const string OVERVIEW = 'overview';

    private const string CORE_OVERVIEW = 'core::Overview';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $sections = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<array{key: string, label: string, component: string}> */
    public function tabs(User $target, ?User $viewer): array
    {
        $tabs = [['key' => self::OVERVIEW, 'label' => __('core::public.profile.tabs.overview'), 'component' => self::CORE_OVERVIEW]];

        foreach ($this->sections() as $moduleName => $sections) {
            $tab = $sections['tab'] ?? null;
            if ($tab === null) {
                continue;
            }

            // componentExists()/label() — внутри try: нестроковые значения дают TypeError, а не 500.
            try {
                $this->requireFields($tab, ['label', 'component']);

                if ($moduleName === self::OVERVIEW || ! $this->componentExists($this->modules, $tab['component'], 'profile')) {
                    continue;
                }

                $tabs[] = ['key' => $moduleName, 'label' => $this->label($tab['label']), 'component' => $tab['component']];
            } catch (Throwable $e) {
                $this->warnOnce("tab:{$moduleName}", "Module [{$moduleName}] has a malformed profile tab: {$e->getMessage()}");
            }
        }

        return $tabs;
    }

    /** @return list<array{key: string, component: string, order: int}> */
    public function overview(User $target, ?User $viewer): array
    {
        $blocks = [];

        foreach ($this->sections() as $moduleName => $sections) {
            $block = $sections['overview'] ?? null;
            if ($block === null) {
                continue;
            }

            try {
                $this->requireFields($block, ['component', 'order']);

                if ($this->componentExists($this->modules, $block['component'], 'profile')) {
                    $blocks[] = ['key' => $moduleName, 'component' => $block['component'], 'order' => $block['order']];
                }
            } catch (Throwable $e) {
                $this->warnOnce("overview:{$moduleName}", "Module [{$moduleName}] has a malformed profile overview block: {$e->getMessage()}");
            }
        }

        /** @var list<array{key: string, component: string, order: int}> */
        return $this->sortItems($blocks, 'key');
    }

    /** @return list<array{key: string, label: string, icon: string, component: string, order: int}> */
    public function actions(User $target, ?User $viewer): array
    {
        // Действия модулей — для чужих профилей: гостям и владельцу не показываются.
        if (! $viewer instanceof User || $viewer->is($target)) {
            return [];
        }

        $items = [];

        foreach ($this->modules->implementing(HasProfileActions::class) as $moduleName => $module) {
            try {
                $actions = $module->profileActions();
            } catch (Throwable $e) {
                $this->warnOnce("module:{$moduleName}", "Module [{$moduleName}] profileActions() failed: {$e->getMessage()}");

                continue;
            }

            foreach ($actions as $action) {
                try {
                    $this->requireFields($action, ['label', 'icon', 'component', 'order']);

                    if (isset($action['permission']) && ! $viewer->can($action['permission'])) {
                        continue;
                    }

                    if (! $this->componentExists($this->modules, $action['component'], 'profile')) {
                        continue;
                    }

                    $items[] = [
                        'key' => $moduleName.':'.explode('::', $action['component'], 2)[1],
                        'label' => $this->label($action['label']),
                        'icon' => $action['icon'],
                        'component' => $action['component'],
                        'order' => $action['order'],
                    ];
                } catch (Throwable $e) {
                    $this->warnOnce("action:{$moduleName}", "Module [{$moduleName}] has a malformed profile action: {$e->getMessage()}");
                }
            }
        }

        /** @var list<array{key: string, label: string, icon: string, component: string, order: int}> */
        return $this->sortItems($items, 'key');
    }

    /** @return list<array{key: string, label: string, value: int|string, tab?: string}> */
    public function counters(User $target, ?User $viewer): array
    {
        $counters = [];

        foreach ($this->sections() as $moduleName => $sections) {
            $closure = $sections['counters'] ?? null;
            if ($closure === null) {
                continue;
            }

            try {
                foreach ($closure($target, $viewer) as $counter) {
                    $this->requireFields($counter, ['key', 'label', 'value']);
                    $counter['label'] = $this->label($counter['label']);
                    $counters[] = $counter;
                }
            } catch (Throwable $e) {
                $this->warnOnce("counters:{$moduleName}", "Module [{$moduleName}] profile counters failed: {$e->getMessage()}");
            }
        }

        return $counters;
    }

    /** Есть ли у зрителя вкладка модуля с таким ключом (`overview` — не вкладка модуля). */
    public function hasModuleTab(User $target, ?User $viewer, string $key): bool
    {
        return array_any($this->tabs($target, $viewer), fn ($tab) => $tab['key'] === $key && $key !== self::OVERVIEW);
    }

    /** @return array<string, mixed> */
    public function tabData(User $target, ?User $viewer, string $key): array
    {
        if (! $this->hasModuleTab($target, $viewer, $key)) {
            return [];
        }

        $module = $this->modules->get($key);
        if (! $module instanceof ModuleServiceProvider || ! method_exists($module, 'profileTabData')) {
            return [];
        }

        try {
            $data = $module->profileTabData($target, $viewer);
        } catch (Throwable $e) {
            $this->warnOnce("tabdata:{$key}", "Module [{$key}] profileTabData() failed: {$e->getMessage()}");

            return [];
        }

        return is_array($data) ? $data : [];
    }

    /** @return array<string, array<string, mixed>> имя модуля → profileSections() (сломанные модули пропущены) */
    private function sections(): array
    {
        if ($this->sections !== null) {
            return $this->sections;
        }

        $all = [];

        foreach ($this->modules->implementing(HasProfileSections::class) as $moduleName => $module) {
            try {
                $all[$moduleName] = $module->profileSections();
            } catch (Throwable $e) {
                $this->warnOnce("module:{$moduleName}", "Module [{$moduleName}] profileSections() failed: {$e->getMessage()}");
            }
        }

        return $this->sections = $all;
    }

    /** Ключ перевода → строка; готовая строка (нет такого ключа) остаётся как есть. */
    private function label(string $label): string
    {
        return __($label);
    }
}
