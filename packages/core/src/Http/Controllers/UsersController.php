<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Admin\TableQuery;
use Ladcms\Core\Http\Resources\PublicUserResource;
use Ladcms\Core\Support\UserModel;

class UsersController
{
    private const array SORTABLE = ['login', 'created_at', 'last_activity_at'];

    private const array SEARCHABLE = ['login', 'name'];

    private const string DEFAULT_SORT = 'created_at';

    private const int PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $sort = $request->query('sort');
        $sort = is_string($sort) && in_array($sort, self::SORTABLE, true) ? $sort : self::DEFAULT_SORT;

        $direction = $request->query('direction');
        $direction = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : 'desc';

        $q = $request->query('q');
        $online = $request->boolean('online');

        $query = UserModel::query()->with('roles');

        if ($online) {
            $query->online();
        }

        TableQuery::apply($query, $request, self::SORTABLE, self::DEFAULT_SORT, self::SEARCHABLE);
        // Стабильный порядок при равных значениях сортируемой колонки.
        $query->orderBy('id', $direction);

        return Inertia::render('core::users/index', [
            'users' => PublicUserResource::collection(TableQuery::paginate($query, $request, self::PER_PAGE)),
            'filters' => [
                'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
                'sort' => $sort,
                'direction' => $direction,
                'online' => $online,
            ],
        ]);
    }
}
