<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Ladcms\Core\Actions\Users\DeleteOwnAccount;

/** Профиль редактируется в core (`settings.profile`); здесь — только удаление аккаунта. */
class ProfileController extends Controller
{
    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Удаление — через core: последнего создателя удалить нельзя, удаление мягкое и попадает в журнал.
        try {
            app(DeleteOwnAccount::class)->handle($user);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'password' => __('The last creator cannot delete their account.'),
            ]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
