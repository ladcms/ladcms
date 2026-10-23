<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Settings;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Ladcms\Core\Actions\Users\RemoveAvatar;
use Ladcms\Core\Actions\Users\UpdateAvatar;
use Ladcms\Core\Models\User;

/**
 * Настройки → аватар. Валидацию делает `UpdateAvatar` (ValidationException уходит в `errors.avatar`).
 * Read-only бан аватар не блокирует: это не публичный текстовый контент.
 */
class AvatarController
{
    public function store(Request $request, #[CurrentUser] User $user, UpdateAvatar $action): RedirectResponse
    {
        $file = $request->file('avatar');
        if (! $request->hasFile('avatar') || ! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['avatar' => __('core::public.settings.avatar.missing_or_too_large')]);
        }

        $action->handle($user, $file);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::public.settings.profile.avatar_saved')]);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, RemoveAvatar $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::public.settings.profile.avatar_removed')]);

        return back();
    }
}
