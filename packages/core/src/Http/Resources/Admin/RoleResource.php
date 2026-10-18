<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'level' => $role->level,
            'color' => $role->color,
            'is_default' => $role->is_default,
            'is_system' => $role->is_system,
            'users_count' => $this->when($role->getAttribute('users_count') !== null, fn () => $role->getAttribute('users_count')),
            'permissions_count' => $this->when($role->getAttribute('permissions_count') !== null, fn () => $role->getAttribute('permissions_count')),
        ];
    }
}
