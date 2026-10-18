<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\Ban;

/** @mixin Ban */
class BanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Ban $ban */
        $ban = $this->resource;

        return [
            'id' => $ban->id,
            'user' => $ban->user === null ? null : ['id' => $ban->user->id, 'login' => $ban->user->login],
            'banned_by' => $ban->bannedBy === null ? null : ['id' => $ban->bannedBy->id, 'login' => $ban->bannedBy->login],
            'type' => $ban->type->value,
            'reason_code' => $ban->reason_code,
            'comment' => $ban->comment,
            'starts_at' => $ban->starts_at->toIso8601String(),
            'expires_at' => $ban->expires_at?->toIso8601String(),
            'is_active' => $ban->isActive(),
        ];
    }
}
