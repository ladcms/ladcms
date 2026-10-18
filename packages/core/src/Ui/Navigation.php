<?php

declare(strict_types=1);

namespace Ladcms\Core\Ui;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasNavigation;
use Ladcms\Core\Module\ModuleRegistry;

/**
 * Навигация публичного layout: пункты ядра и модулей с HasNavigation. Пункты с недостающим правом
 * отбрасываются (гостю — все, требующие права), подписи берутся из `<module>::public.nav.<key>`.
 *
 * @phpstan-type NavItem array{key: string, label: string, icon: string, url: string, order: int, active: bool}
 */
class Navigation
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<NavItem> */
    public function items(?User $user, Request $request): array
    {
        $items = [];

        foreach ($this->modules->implementing(HasNavigation::class) as $name => $module) {
            foreach ($module->navigation() as $entry) {
                $permission = $entry['permission'] ?? null;
                if ($permission !== null && (! $user instanceof User || ! $user->can($permission))) {
                    continue;
                }

                $route = $entry['route'];
                if (! Route::has($route) && ! isset($entry['path'])) {
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
            }
        }

        usort($items, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $items;
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
