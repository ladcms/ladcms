<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

/**
 * Контроллеры списков обязаны делать `with('roles')` и `->withActiveBanFlag()`,
 * иначе на каждую строку уходят отдельные запросы.
 *
 * @mixin User
 */
class UserListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $roles = [];
        foreach ($user->roles as $role) {
            if ($role instanceof Role) {
                $roles[] = ['name' => $role->name, 'color' => $role->color, 'level' => $role->level];
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
            'is_online' => $user->isOnline(),
            'is_banned' => $user->getAttribute('is_banned') !== null
                ? (bool) $user->getAttribute('is_banned')
                : $user->isBanned(),
            'is_suspicious' => $user->is_suspicious,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_activity_at' => $user->last_activity_at?->toIso8601String(),
        ];
    }
}
