<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController
{
    public function __invoke(): Response
    {
        return Inertia::render('core::welcome', ['version' => config('core.version')]);
    }
}
