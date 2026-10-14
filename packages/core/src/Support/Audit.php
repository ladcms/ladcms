<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Support\ActivityLogger;

/** Журнал административных действий (spatie/laravel-activitylog, log name `admin`). */
final class Audit
{
    public const string LOG_NAME = 'admin';

    /** @param  array<string, mixed>  $properties */
    public static function log(string $event, User $actor, ?Model $subject = null, array $properties = []): void
    {
        activity(self::LOG_NAME)
            ->causedBy($actor)
            ->when($subject instanceof Model, fn (ActivityLogger $logger): ActivityLogger => $logger->performedOn($subject))
            ->withProperties($properties)
            ->event($event)
            ->log($event);
    }
}
