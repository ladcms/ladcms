<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\User;

/**
 * Текущий пользователь для фронтенда (Inertia shared `auth.user`).
 *
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'login' => $user->login,
            'name' => $user->name,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'is_banned' => $user->isBanned(),
            'can_write' => $user->canWrite(),
        ];
    }
}
