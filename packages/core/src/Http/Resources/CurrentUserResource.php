<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\Role;
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
        // Активный бан — один запрос на оба флага.
        $banned = $user->activeBan() instanceof Ban;

        $roles = [];
        foreach ($user->roles as $role) {
            if ($role instanceof Role) {
                $roles[] = ['name' => $role->name, 'color' => $role->color];
            }
        }

        return [
            'id' => $user->id,
            'login' => $user->login,
            'name' => $user->name,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'roles' => $roles,
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'level' => $user->level(),
            'is_creator' => $user->isCreator(),
            'is_banned' => $banned,
            'can_write' => ! $banned && $user->readonlyPeriodPassed(),
        ];
    }
}
