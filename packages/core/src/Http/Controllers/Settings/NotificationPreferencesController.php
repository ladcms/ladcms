<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Settings;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Actions\Users\UpdateNotificationPreferences;
use Ladcms\Core\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use Ladcms\Core\Models\User;
use Ladcms\Core\Support\NotificationTypes;

class NotificationPreferencesController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $types = array_keys(NotificationTypes::all());

        $preferences = [];
        foreach ($types as $type) {
            $preferences[$type] = [
                'database' => $type === UpdateNotificationPreferences::LOCKED_TYPE || $user->wantsNotification($type, 'database'),
                'mail' => $user->wantsNotification($type, 'mail'),
            ];
        }

        return Inertia::render('core::settings/notifications', [
            'types' => $types,
            'preferences' => $preferences,
            'locked' => [UpdateNotificationPreferences::LOCKED_TYPE.'.database'],
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request, #[CurrentUser] User $user, UpdateNotificationPreferences $action): RedirectResponse
    {
        $action->handle($user, $request->preferences());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::public.settings.saved')]);

        return to_route('settings.notifications');
    }
}
