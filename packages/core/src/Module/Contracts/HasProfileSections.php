<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

use Closure;
use Ladcms\Core\Models\User;

/**
 * Секции публичного профиля `/u/{login}`. Компоненты — `<module>::<Name>` → `resources/js/profile/<Name>.tsx`.
 *
 * - `tab`: вкладка профиля (ключ вкладки = имя модуля, `?tab=<module>`); `label` — ключ перевода
 *   (`<module>::public.profile.*`) или готовая строка; компонент получает `{profile, tab_data}`.
 * - `overview`: блок на вкладке «Обзор»; компонент получает `{profile}`.
 * - `counters`: счётчики в шапке профиля, замыкание получает `(User $target, ?User $viewer)`; `tab` — ключ вкладки, куда ведёт счётчик.
 *
 * Опционально модуль может реализовать метод `profileTabData(User $target, ?User $viewer): array` —
 * данные вкладки (props `tab_data`). Они считаются только при открытой вкладке модуля (`?tab=<module>`);
 * метод проверяется через `method_exists`, в интерфейс не входит.
 */
interface HasProfileSections
{
    /**
     * @return array{
     *     tab?: array{label: string, component: string},
     *     overview?: array{component: string, order: int},
     *     counters?: Closure(User, ?User): list<array{key: string, label: string, value: int|string, tab?: string}>
     * }
     */
    public function profileSections(): array;
}
