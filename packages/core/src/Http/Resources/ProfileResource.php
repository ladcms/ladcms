<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources;

use Illuminate\Http\Request;
use Ladcms\Core\Models\User;
use Ladcms\Core\Ui\ProfileSections;

/**
 * Профиль для страницы `/u/{login}`: `PublicUserResource` (правила приватности наследуются) плюс анкета,
 * публичная отметка бана (без причины), подозрительность — только для `users.view`, счётчики модулей.
 *
 * @mixin User
 */
class ProfileResource extends PublicUserResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $data = parent::toArray($request) + [
            'about' => $user->about,
            'signature' => $user->signature,
            'sex' => $user->sex?->value,
            'language' => $user->language,
            'last_activity_at' => $user->last_activity_at?->toIso8601String(),
            'is_banned' => $user->isBanned(),
        ];

        $viewer = $request->user();
        if ($viewer instanceof User && $viewer->can('users.view')) {
            $data['is_suspicious'] = $user->is_suspicious;
        }

        $data['counters'] = app(ProfileSections::class)->counters($user, $viewer instanceof User ? $viewer : null);

        return $data;
    }
}
