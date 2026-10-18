<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Ladcms\Core\Actions\Moderation\BanUser;
use Ladcms\Core\Actions\Moderation\LiftBan;
use Ladcms\Core\Http\Requests\Admin\BanUserRequest;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

class UserBanController
{
    public function store(BanUserRequest $request, #[CurrentUser] User $actor, User $user, BanUser $action): RedirectResponse
    {
        $comment = $request->validated('comment');

        $action->handle(
            $actor,
            $user,
            $request->banType(),
            (string) $request->validated('reason_code'),
            $request->expiresAt(),
            is_string($comment) ? $comment : null,
        );

        return $this->saved();
    }

    /** `{ban}` привязан к `{user}` (scoped binding): чужой бан → 404. */
    public function destroy(#[CurrentUser] User $actor, User $user, Ban $ban, LiftBan $action): RedirectResponse
    {
        $action->handle($actor, $ban);

        return $this->saved();
    }

    private function saved(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return back();
    }
}
