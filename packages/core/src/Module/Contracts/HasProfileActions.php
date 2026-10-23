<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

/**
 * Кнопки действий в шапке чужого профиля (гостям не показываются). Компонент — `<module>::<Name>` →
 * `resources/js/profile/<Name>.tsx`, получает `{profile}`. Необязательный `permission` скрывает кнопку
 * от зрителей без этого права.
 */
interface HasProfileActions
{
    /** @return list<array{label: string, icon: string, component: string, order: int, permission?: string}> */
    public function profileActions(): array;
}
