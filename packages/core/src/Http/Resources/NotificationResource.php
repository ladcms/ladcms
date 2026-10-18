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

        return [
            'id' => $notification->getKey(),
            'type' => class_basename($type),
            'data' => $data,
            'read_at' => $readAt?->toIso8601String(),
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }
}
