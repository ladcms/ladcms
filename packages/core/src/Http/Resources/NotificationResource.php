<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /** Title keys for rows stored before notifications carried their own `title`. */
    private const array LEGACY_TITLES = [
        'UserMentioned' => 'core::public.notifications.mentioned',
        'ComplaintFiled' => 'core::public.notifications.complaint',
        'UserBanned' => 'core::public.notifications.banned',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function legacyParams(string $basename, array $data): array
    {
        return $basename === 'UserMentioned' && isset($data['author_login']) ? ['name' => $data['author_login']] : [];
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;

        /** @var string $type */
        $type = $notification->getAttribute('type');
        /** @var array<string, mixed> $data */
        $data = $notification->getAttribute('data');
        /** @var CarbonInterface|null $readAt */
        $readAt = $notification->getAttribute('read_at');
        /** @var CarbonInterface|null $createdAt */
        $createdAt = $notification->getAttribute('created_at');

        $basename = class_basename($type);
        $title = $data['title'] ?? null;
        $params = $data['params'] ?? null;
        $url = $data['url'] ?? null;

        return [
            'id' => $notification->getKey(),
            'type' => $basename,
            'title' => is_string($title) && $title !== '' ? $title : self::LEGACY_TITLES[$basename] ?? 'core::public.notifications.generic',
            'params' => is_array($params) ? $params : $this->legacyParams($basename, $data),
            'url' => is_string($url) && $url !== '' ? $url : null,
            'data' => $data,
            'read_at' => $readAt?->toIso8601String(),
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }
}
