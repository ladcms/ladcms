<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

/**
 * Модуль с разделом в админке.
 *
 * `label` — ключ перевода (или готовая строка), `icon` — имя иконки lucide в kebab-case,
 * `route` — имя роута. Необязательные поля: `key` (идентификатор пункта, по умолчанию — последний
 * сегмент имени роута), `permission` (пункт виден только при праве), `path` (url, если роут ещё
 * не зарегистрирован), `params` (параметры роута / query).
 *
 * @phpstan-type AdminMenuChild array{label: string, route: string, icon?: string, order?: int, key?: string, permission?: string, path?: string, params?: array<string, string>}
 * @phpstan-type AdminMenuEntry array{label: string, icon: string, route: string, order: int, key?: string, permission?: string, path?: string, params?: array<string, string>, children?: list<AdminMenuChild>}
 */
interface HasAdminPanel
{
    /** @return list<AdminMenuEntry> */
    public function adminMenu(): array;
}
