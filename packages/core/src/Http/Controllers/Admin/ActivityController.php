<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Admin\ActivitySubjects;
use Ladcms\Core\Admin\TableQuery;
use Ladcms\Core\Http\Resources\Admin\ActivityResource;
use Ladcms\Core\Models\User;
use Spatie\Activitylog\Models\Activity;

class ActivityController
{
    private const array SORTABLE = ['created_at', 'event'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'causer' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Activity::query()->with('causer');

        if (filled($filters['causer'] ?? null)) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['causer']).'%';
            $query->whereHasMorph('causer', [User::class], function (Builder $q) use ($like): void {
                $q->whereRaw("login LIKE ? ESCAPE '!'", [$like]);
            });
        }
        if (filled($filters['event'] ?? null)) {
            $query->where('event', $filters['event']);
        }
        if (filled($filters['from'] ?? null)) {
            $query->where('created_at', '>=', Date::parse($filters['from'])->startOfDay());
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('created_at', '<=', Date::parse($filters['to'])->endOfDay());
        }

        TableQuery::apply($query, $request, self::SORTABLE, 'created_at');
        $query->orderByDesc('id');

        $page = TableQuery::paginate($query, $request);
        ActivitySubjects::load($page->items());

        $sort = $request->query('sort');
        $direction = $request->query('direction');

        return Inertia::render('core::admin/activity/index', [
            'activity' => ActivityResource::collection($page),
            'filters' => [
                'causer' => $filters['causer'] ?? null,
                'event' => $filters['event'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'sort' => is_string($sort) && in_array($sort, self::SORTABLE, true) ? $sort : 'created_at',
                'direction' => is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true) ? strtolower($direction) : 'desc',
            ],
            'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event')->all(),
        ]);
    }
}
