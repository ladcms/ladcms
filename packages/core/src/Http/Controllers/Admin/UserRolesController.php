<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;
use Ladcms\Core\Actions\Users\AssignRoles;
use Ladcms\Core\Http\Requests\Admin\AssignRolesRequest;
use Ladcms\Core\Models\User;

class UserRolesController
{
    public function update(AssignRolesRequest $request, #[CurrentUser] User $actor, User $user, AssignRoles $action): RedirectResponse
    {
        try {
            $action->handle($actor, $user, $request->roleNames());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return back();
    }
}
