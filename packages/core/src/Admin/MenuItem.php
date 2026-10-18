<?php

declare(strict_types=1);

namespace Ladcms\Core\Admin;

/** Пункт админ-меню, готовый к отдаче на фронтенд (переведённая подпись, url, активность). */
final readonly class MenuItem
{
    /** @param  list<MenuItem>  $children */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $route,
        public string $url,
        public int $order = 0,
        public ?string $permission = null,
        public array $children = [],
        public bool $active = false,
        public ?int $badge = null,
    ) {}

    /** @return array{key: string, label: string, icon: string, route: string, url: string, order: int, permission: ?string, badge: ?int, active: bool, children: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'route' => $this->route,
            'url' => $this->url,
            'order' => $this->order,
            'permission' => $this->permission,
            'badge' => $this->badge,
            'active' => $this->active,
            'children' => array_map(fn (MenuItem $child): array => $child->toArray(), $this->children),
        ];
    }
}
