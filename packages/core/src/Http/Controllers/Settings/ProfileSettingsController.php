<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Settings;

use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Actions\Users\ChangeOwnLogin;
use Ladcms\Core\Actions\Users\UpdateOwnProfile;
use Ladcms\Core\Http\Requests\Settings\ChangeOwnLoginRequest;
use Ladcms\Core\Http\Requests\Settings\UpdateProfileRequest;
use Ladcms\Core\Models\User;

/** Настройки → «Профиль»: анкета, email, логин. Права на публичный контент проверяет `UpdateOwnProfile` (403 для read-only). */
class ProfileSettingsController
{
    public function edit(Request $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('core::settings/profile', [
            'profile' => [
                'login' => $user->login,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
                'sex' => $user->sex?->value,
                'birth_date' => $user->birth_date?->toDateString(),
                'about' => $user->about,
                'signature' => $user->signature,
                'timezone' => $user->timezone,
            ],
            'avatar_url' => $user->avatar_url,
            'avatar_max_kb' => (int) config('core.avatars.max_kb', 2048),
            'avatar_max_dimension' => (int) config('core.avatars.max_dimension', 4000),
            'must_verify_email' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateProfileRequest $request, #[CurrentUser] User $user, UpdateOwnProfile $action): RedirectResponse
    {
        $action->handle($user, $request->profileData());

        return $this->saved('settings.profile');
    }

    public function updateLogin(ChangeOwnLoginRequest $request, #[CurrentUser] User $user, ChangeOwnLogin $action): RedirectResponse
    {
        $action->handle($user, $request->login());

        return $this->saved('settings.profile');
    }

    private function saved(string $route): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::public.settings.saved')]);

        return to_route($route);
    }
}
