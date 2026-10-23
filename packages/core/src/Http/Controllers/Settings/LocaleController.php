<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Ladcms\Core\Actions\Users\UpdateLocale;
use Ladcms\Core\Http\Requests\Settings\UpdateLocaleRequest;
use Ladcms\Core\Models\User;

/** Смена языка: доступна и гостям (cookie), поэтому без `auth`. */
class LocaleController
{
    public function update(UpdateLocaleRequest $request, UpdateLocale $action): RedirectResponse
    {
        $user = $request->user();
        $action->handle($user instanceof User ? $user : null, $request->locale());

        return back();
    }
}
