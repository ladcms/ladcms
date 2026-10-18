<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('core::admin/dashboard', ['stats' => []]);
    }
}
