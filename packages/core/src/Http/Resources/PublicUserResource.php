<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

/**
 * Единственное место публичных правил приватности: `email` и `birth_date` отдаются только владельцу
 * или при включённых `show_email` / `show_birthday`. Подозрительность и сырая активность здесь не отдаются; статус бана — только публичный булев `is_banned` в `ProfileResource` (без причины).
 * Контроллеры списков обязаны делать `with('roles')`.
 *
 * @mixin User
 */
class PublicUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $roles = [];
        foreach ($user->roles as $role) {
            if ($role instanceof Role) {
                $roles[] = ['name' => $role->name, 'color' => $role->color];
            }
        }

        $data = [
            'id' => $user->id,
            'login' => $user->login,
            'name' => $user->name,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_url,
            'roles' => $roles,
            'is_online' => $user->isOnline(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        $isSelf = $request->user()?->getAuthIdentifier() === $user->getKey();

        if ($isSelf || $user->show_email === true) {
            $data['email'] = $user->email;
        }

        if (($isSelf || $user->show_birthday === true) && $user->birth_date !== null) {
            $data['birth_date'] = $user->birth_date->toDateString();
        }

        return $data;
    }
}
