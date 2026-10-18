<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources\Admin;

use Illuminate\Http\Request;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;

/** @mixin User */
class UserDetailResource extends UserListResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $activeBan = $user->activeBan();

        return [
            ...parent::toArray($request),
            'sex' => $user->sex?->value,
            'birth_date' => $user->birth_date?->toDateString(),
            'about' => $user->about,
            'signature' => $user->signature,
            'language' => $user->language,
            'timezone' => $user->timezone,
            'registered_ip' => $user->registered_ip,
            'suspicious_note' => $user->suspicious_note,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'active_ban' => $activeBan === null ? null : BanResource::make($activeBan)->resolve($request),
            'bans_count' => $user->getAttribute('bans_count') ?? $user->bans()->count(),
            'complaints_count' => $user->getAttribute('complaints_count')
                ?? Complaint::query()->where('target_user_id', $user->id)->count(),
        ];
    }
}
