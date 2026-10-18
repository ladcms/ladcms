<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Actions\Settings\UpdateSettingsGroup;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\Schema\SettingsSchema;
use Ladcms\Core\Settings\SettingsGroups;

/**
 * Формы настроек, построенные из Settings-классов всех модулей. Неизвестная группа → 404,
 * ошибки валидации `UpdateSettingsGroup` всплывают как обычные ошибки формы.
 */
class SettingsController
{
    public function edit(string $group, SettingsGroups $groups): Response
    {
        $current = $groups->resolve($group) ?? abort(404);

        return Inertia::render('core::admin/settings/edit', [
            'group' => $group,
            'groups' => array_map(fn (array $item): array => [
                'key' => $item['key'],
                'label' => $item['label'],
                'href' => route('admin.settings.edit', ['group' => $item['key']]),
            ], $groups->all()),
            'schema' => SettingsSchema::for($current['class']),
        ]);
    }

    public function update(Request $request, string $group, #[CurrentUser] User $actor, SettingsGroups $groups, UpdateSettingsGroup $action): RedirectResponse
    {
        $current = $groups->resolve($group) ?? abort(404);

        $action->handle($actor, $current['class'], $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return back();
    }
}
