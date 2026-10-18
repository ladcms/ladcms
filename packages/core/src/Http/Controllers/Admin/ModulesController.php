<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Ladcms\Core\Actions\Modules\ToggleModule;
use Ladcms\Core\Admin\ModulesOverview;
use Ladcms\Core\Http\Requests\Admin\ToggleModuleRequest;
use Ladcms\Core\Models\User;

class ModulesController
{
    public function index(ModulesOverview $overview): Response
    {
        return Inertia::render('core::admin/modules/index', [
            'modules' => $overview->rows(),
            // Роуты модулей регистрируются при загрузке; при route:cache переключение вступит в силу
            // только после сброса кеша — Artisan из веб-запроса не вызываем, а предупреждаем.
            'routes_cached' => app()->routesAreCached(),
        ]);
    }

    public function toggle(ToggleModuleRequest $request, string $name, #[CurrentUser] User $actor, ToggleModule $action): RedirectResponse
    {
        try {
            $action->handle($actor, $name, $request->isEnabled());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return back();
    }
}
