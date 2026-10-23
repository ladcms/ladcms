<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Settings;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Actions\Users\UpdatePrivacy;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Http\Requests\Settings\UpdatePrivacyRequest;
use Ladcms\Core\Models\User;

class PrivacyController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('core::settings/privacy', [
            'privacy' => [
                'show_email' => $user->show_email,
                'show_birthday' => $user->show_birthday,
                'messages_from' => $user->messages_from->value,
            ],
            'messages_from_options' => array_column(MessagesFrom::cases(), 'value'),
        ]);
    }

    public function update(UpdatePrivacyRequest $request, #[CurrentUser] User $user, UpdatePrivacy $action): RedirectResponse
    {
        $action->handle($user, $request->privacyData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::public.settings.saved')]);

        return to_route('settings.privacy');
    }
}
