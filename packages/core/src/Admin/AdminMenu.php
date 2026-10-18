<?php

declare(strict_types=1);

namespace Ladcms\Core\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\Contracts\HasAdminPanel;
use Ladcms\Core\Module\ModuleRegistry;
use Ladcms\Core\Module\ModuleServiceProvider;
use Ladcms\Core\Settings\SettingsGroups;

/**
 * Собирает меню админки: разделы ядра (из CoreServiceProvider::adminMenu()), затем по секции на каждый
 * модуль с HasAdminPanel. Пункты без права пользователя отбрасываются, текущий помечается `active`.
 *
 * @phpstan-import-type AdminMenuEntry from HasAdminPanel
 * @phpstan-import-type AdminMenuChild from HasAdminPanel
 */
class AdminMenu
{
    /** Число pending-жалоб на время одного `build()`: `false` — ещё не считали. */
    private int|false|null $pendingComplaints = false;

    public function __construct(private readonly ModuleRegistry $modules, private readonly SettingsGroups $settingsGroups) {}

    /** @return list<MenuItem> */
    public function build(User $user, Request $request): array
    {
        $menu = [];
        $this->pendingComplaints = false;

        $core = $this->modules->get('core');
        if ($core instanceof HasAdminPanel) {
            foreach ($this->sorted($core->adminMenu()) as $entry) {
                $children = ($entry['key'] ?? null) === 'settings' ? $this->settingsChildren() : ($entry['children'] ?? []);
                $item = $this->item($entry, $children, $user, $request);
                if ($item instanceof MenuItem) {
                    $menu[] = $item;
                }
            }
        }

        foreach ($this->modules->implementing(HasAdminPanel::class) as $name => $module) {
            if ($name === 'core') {
                continue;
            }

            $item = $this->moduleSection($module, $user, $request);
            if ($item instanceof MenuItem) {
                $menu[] = $item;
            }
        }

        return $menu;
    }

    /**
     * Секция модуля: подпись — имя модуля, пункты его adminMenu() — дети.
     *
     * @param  ModuleServiceProvider&HasAdminPanel  $module
     */
    private function moduleSection(ModuleServiceProvider $module, User $user, Request $request): ?MenuItem
    {
        $name = $module->name();
        $children = $this->children($this->sorted($module->adminMenu()), $user, $request);
        if ($children === []) {
            return null;
        }

        $first = $children[0];

        return new MenuItem(
            key: $name,
            label: $this->translate("{$name}::admin.title", Str::headline($name)),
            icon: $first->icon,
            route: $first->route,
            url: $first->url,
            order: $first->order,
            children: $children,
            active: $this->anyActive($children),
        );
    }

    /**
     * @param  AdminMenuEntry  $entry
     * @param  list<AdminMenuChild>  $children
     */
    private function item(array $entry, array $children, User $user, Request $request): ?MenuItem
    {
        $permission = $entry['permission'] ?? null;
        if ($permission !== null && ! $user->can($permission)) {
            return null;
        }

        $childItems = $this->children($children, $user, $request);
        $key = $entry['key'] ?? Str::afterLast($entry['route'], '.');

        return new MenuItem(
            key: $key,
            label: __($entry['label']),
            icon: $entry['icon'],
            route: $entry['route'],
            url: $this->url($entry),
            order: $entry['order'],
            permission: $permission,
            children: $childItems,
            active: $this->anyActive($childItems) || $this->matches($entry, $request),
            badge: $key === 'moderation' ? $this->pendingComplaints($user) : null,
        );
    }

    /**
     * Дочерние пункты: фильтр по праву; из нескольких совпавших с запросом активным остаётся
     * самый специфичный (с бо́льшим числом параметров) — «Все» и «Подозрительные» ведут на один роут.
     *
     * @param  list<AdminMenuChild>  $children
     * @return list<MenuItem>
     */
    private function children(array $children, User $user, Request $request): array
    {
        $visible = array_values(array_filter(
            $children,
            fn (array $child): bool => ! isset($child['permission']) || $user->can($child['permission']),
        ));

        $activeIndex = null;
        $activeWeight = -1;
        foreach ($visible as $i => $child) {
            $weight = count($child['params'] ?? []);
            if ($weight > $activeWeight && $this->matches($child, $request)) {
                $activeIndex = $i;
                $activeWeight = $weight;
            }
        }

        $items = [];
        foreach ($visible as $i => $child) {
            $key = $child['key'] ?? Str::afterLast($child['route'], '.');
            $items[] = new MenuItem(
                key: $key,
                label: __($child['label']),
                icon: $child['icon'] ?? 'circle',
                route: $child['route'],
                url: $this->url($child),
                order: $child['order'] ?? 0,
                permission: $child['permission'] ?? null,
                active: $i === $activeIndex,
                badge: $child['route'] === 'admin.moderation.complaints.index' ? $this->pendingComplaints($user) : null,
            );
        }

        return $items;
    }

    /**
     * Группы настроек всех HasSettings-модулей.
     *
     * @return list<AdminMenuChild>
     */
    private function settingsChildren(): array
    {
        return array_map(fn (array $group): array => [
            'key' => $group['key'],
            'label' => $group['label'],
            'route' => 'admin.settings.edit',
            'path' => '/admin/settings/'.$group['key'],
            'params' => ['group' => $group['key']],
        ], $this->settingsGroups->all());
    }

    /** @param  AdminMenuEntry|AdminMenuChild  $entry */
    private function url(array $entry): string
    {
        $params = $entry['params'] ?? [];
        if (Route::has($entry['route'])) {
            return route($entry['route'], $params);
        }

        $url = url($entry['path'] ?? '/admin');
        // Параметры, которые не попали в путь, уходят в query — как это сделал бы route().
        $query = array_filter($params, fn (string $value): bool => ! str_ends_with($url, '/'.$value));

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * Пункт совпадает с запросом: по имени роута (для `*.index` — вся группа `*.`), иначе по url-префиксу;
     * указанные `params` должны совпасть с параметрами роута или query.
     *
     * @param  AdminMenuEntry|AdminMenuChild  $entry
     */
    private function matches(array $entry, Request $request): bool
    {
        $route = $entry['route'];
        $patterns = [$route, $route.'.*'];
        if (str_ends_with($route, '.index')) {
            $patterns[] = Str::beforeLast($route, '.index').'.*';
        }

        if (Route::has($route)) {
            if (! $request->routeIs(...$patterns)) {
                return false;
            }
        } else {
            $path = trim((string) parse_url(url($entry['path'] ?? '/admin'), PHP_URL_PATH), '/');
            $current = trim($request->path(), '/');
            if ($current !== $path && ! str_starts_with($current, $path.'/')) {
                return false;
            }
        }

        foreach ($entry['params'] ?? [] as $param => $value) {
            $actual = $request->route()?->parameter($param) ?? $request->query($param);
            if ((string) (is_scalar($actual) ? $actual : '') !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param  list<MenuItem>  $items */
    private function anyActive(array $items): bool
    {
        return array_any($items, fn (MenuItem $item): bool => $item->active);
    }

    /**
     * @template T of array{order?: int}
     *
     * @param  list<T>  $entries
     * @return list<T>
     */
    private function sorted(array $entries): array
    {
        usort($entries, fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $entries;
    }

    private function pendingComplaints(User $user): ?int
    {
        if ($this->pendingComplaints === false) {
            $count = $user->can('complaints.review') ? Complaint::query()->pending()->count() : 0;
            $this->pendingComplaints = $count > 0 ? $count : null;
        }

        return $this->pendingComplaints;
    }

    private function translate(string $key, string $fallback): string
    {
        return Lang::has($key) ? (string) __($key) : $fallback;
    }
}
