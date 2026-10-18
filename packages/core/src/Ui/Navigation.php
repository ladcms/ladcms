<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasNavigation;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Ui\Concerns\UiRegistry;
use Throwable;

/**
 * Навигация публичного layout: пункты ядра и модулей с HasNavigation. Пункты с недостающим правом
 * отбрасываются (гостю — все, требующие права), подписи берутся из `<module>::public.nav.<key>`.
 *
 * @phpstan-type NavItem array{key: string, label: string, icon: string, url: string, order: int, active: bool}
 */
class Navigation
{
    use UiRegistry;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<NavItem> */
    public function items(?User $user, Request $request): array
    {
        $items = [];

        foreach ($this->modules->implementing(HasNavigation::class) as $name => $module) {
            try {
                $entries = $module->navigation();
            } catch (Throwable $e) {
                $this->warnOnce("module:{$name}", "Module [{$name}] navigation() failed: {$e->getMessage()}");

                continue;
            }

            foreach ($entries as $entry) {
                try {
                    $this->requireFields($entry, ['key', 'label', 'icon', 'route', 'order']);
                    $permission = $entry['permission'] ?? null;
                    if ($permission !== null && (! $user instanceof User || ! $user->can($permission))) {
                        continue;
                    }

                    if (! Route::has($entry['route']) && ! isset($entry['path'])) {
                        $this->warnOnce("entry:{$name}:{$entry['key']}", "Module [{$name}] navigation item [{$entry['key']}] has no route or path; skipped.");

                        continue;
                    }

                    $key = "{$name}::public.nav.{$entry['key']}";

                    $items[] = [
                        'key' => $entry['key'],
                        'label' => Lang::has($key) ? (string) __($key) : $entry['label'],
                        'icon' => $entry['icon'],
                        'url' => $this->url($entry),
                        'order' => $entry['order'],
                        'active' => $this->active($entry, $request),
                    ];
                } catch (Throwable $e) {
                    $this->warnOnce("entry:{$name}:".Arr::get($entry, 'key', '?'), "Module [{$name}] navigation item skipped: {$e->getMessage()}");
                }
            }
        }

        /** @var list<NavItem> */
        return $this->sortItems($items, 'key');
    }

    /** @param  array{route: string, params?: array<string, scalar>, path?: string}  $entry */
    private function url(array $entry): string
    {
        return Route::has($entry['route'])
            ? route($entry['route'], $entry['params'] ?? [])
            : url($entry['path'] ?? '/');
    }

    /** @param  array{route: string, path?: string}  $entry */
    private function active(array $entry, Request $request): bool
    {
        if (Route::has($entry['route'])) {
            return $request->routeIs($entry['route'], $entry['route'].'.*');
        }

        $path = trim($entry['path'] ?? '', '/');
        $current = trim($request->path(), '/');

        return $path !== '' && ($current === $path || str_starts_with($current, $path.'/'));
    }
}
