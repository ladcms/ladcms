<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Models\Activity;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;
        $causer = $activity->causer;
        // subject подгружается заранее и только для существующих классов; ленивая загрузка уронила бы страницу.
        $subject = $activity->relationLoaded('subject') ? $activity->subject : null;

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'event' => $activity->event,
            'description' => $activity->description,
            'causer' => $causer instanceof User ? ['id' => $causer->id, 'login' => $causer->login] : null,
            'subject' => $activity->subject_type === null ? null : array_filter([
                'type' => class_basename($activity->subject_type),
                'id' => $activity->subject_id,
                'login' => $subject instanceof User ? $subject->login : null,
            ], fn ($v): bool => $v !== null),
            'properties' => $activity->properties,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
