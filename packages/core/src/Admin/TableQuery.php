<?php

declare(strict_types=1);

namespace Ladcms\Core\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Серверная сортировка, поиск и пагинация для админ-таблиц.
 */
final class TableQuery
{
    private const int SEARCH_MAX_LENGTH = 100;

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $q
     * @param  list<string>  $sortable
     * @param  list<string>  $searchable
     * @return Builder<TModel>
     */
    public static function apply(
        Builder $q,
        Request $r,
        array $sortable,
        string $defaultSort,
        array $searchable = [],
        string $defaultDirection = 'desc',
    ): Builder {
        $sort = $r->query('sort');
        $sort = is_string($sort) && in_array($sort, $sortable, true) ? $sort : $defaultSort;

        $direction = $r->query('direction');
        $direction = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : $defaultDirection;

        $term = $r->query('q');
        $term = is_string($term) ? mb_substr(trim($term), 0, self::SEARCH_MAX_LENGTH) : '';

        if ($term !== '' && $searchable !== []) {
            // `!` как escape-символ: обратный слэш в литерале ESCAPE ломается в MySQL.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

            $q->where(function (Builder $query) use ($searchable, $like): void {
                foreach ($searchable as $column) {
                    $query->orWhereRaw($query->getGrammar()->wrap($column)." LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }

        return $q->orderBy($sort, $direction);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $q
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function paginate(Builder $q, Request $r, int $perPage = 25): LengthAwarePaginator
    {
        return $q->paginate($perPage, ['*'], 'page', $r->integer('page', 1))->appends($r->query());
    }
}
