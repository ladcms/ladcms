<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

use Closure;
use Ladcms\Core\Models\User;

interface HasWidgets
{
    /**
     * `component` — `<module>::<Name>`, файл `resources/js/widgets/<Name>.tsx` модуля (без вложенных каталогов).
     * `props` — массив или замыкание `fn (?User $user): array`, вычисляется лениво, только для запрошенного слота.
     *
     * @return list<array{name: string, component: string, slot: 'sidebar'|'main', order: int, props?: Closure|array<string, mixed>}>
     */
    public function widgets(): array;
}
