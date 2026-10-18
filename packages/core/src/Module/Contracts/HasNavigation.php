<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasNavigation
{
    /**
     * `route` — имя роута; если его нет, пункт использует `path` (литеральный url), а без него отбрасывается.
     * `permission` — право, без которого пункт скрыт (гостю — всегда).
     * Подпись берётся из `<module>::public.nav.<key>`, `label` — запасной вариант.
     *
     * @return list<array{key: string, label: string, icon: string, route: string, order: int, params?: array<string, scalar>, permission?: string, path?: string}>
     */
    public function navigation(): array;
}
