<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Settings\GeneralSettings;

class RulesController
{
    public function show(GeneralSettings $settings): Response
    {
        $markdown = trim($settings->rules_markdown);

        return Inertia::render('core::rules', [
            // Sanitised here (raw html stripped, unsafe links dropped): the client renders it as-is.
            'html' => $markdown === '' ? null : Str::markdown($markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 10,
            ]),
        ]);
    }
}
