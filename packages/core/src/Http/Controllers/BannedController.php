<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

class BannedController
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $ban = $user instanceof User ? $user->activeBan() : null;

        if (! $ban instanceof Ban) {
            return redirect()->route('home');
        }

        return Inertia::render('core::banned', [
            'reason_code' => $ban->reason_code,
            'comment' => $ban->comment,
            'expires_at' => $ban->expires_at?->toIso8601String(),
        ]);
    }
}
