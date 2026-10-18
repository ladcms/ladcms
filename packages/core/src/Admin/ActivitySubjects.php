<?php

declare(strict_types=1);

namespace Ladcms\Core\Admin;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Activitylog\Models\Activity;

/**
 * Подгружает `subject` только у записей, чей класс ещё существует:
 * запись удалённого модуля иначе роняет страницу с «Class not found».
 */
final class ActivitySubjects
{
    /** @param  iterable<Activity>  $activities */
    public static function load(iterable $activities): void
    {
        $loadable = [];

        foreach ($activities as $activity) {
            $type = $activity->subject_type;
            if ($type === null) {
                continue;
            }

            $class = Relation::getMorphedModel($type) ?? $type;
            if (class_exists($class)) {
                $loadable[] = $activity;
            }
        }

        new EloquentCollection($loadable)->load('subject');
    }
}
