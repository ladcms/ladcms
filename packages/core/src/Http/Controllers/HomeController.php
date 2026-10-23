<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** Главная: виджеты слота `main` приходят из shared-пропа `layout.widgets.main`, своих пропсов у страницы нет. */
class HomeController
{
    public function __invoke(): Response
    {
        return Inertia::render('core::home');
    }
}
