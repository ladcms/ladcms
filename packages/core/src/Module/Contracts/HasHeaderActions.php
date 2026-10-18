<?php

declare(strict_types=1);

namespace Ladcms\Core\Module\Contracts;

interface HasHeaderActions
{
    /**
     * `component` — `<module>::<Name>`, файл `resources/js/header-actions/<Name>.tsx` модуля.
     * `auth` — показывать только авторизованным (по умолчанию `false`).
     *
     * @return list<array{key: string, icon: string, component: string, order: int, auth?: bool}>
     */
    public function headerActions(): array;
}
