<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Admin\TableQuery;
use Ladcms\Core\Http\Resources\Admin\RoleResource;
use Ladcms\Core\Http\Resources\Admin\UserListResource;
use Ladcms\Core\Models\Role;
use Ladcms\Core\Models\User;

class UsersController
{
    private const array SORTABLE = ['login', 'name', 'email', 'created_at', 'last_activity_at'];

    private const array SEARCHABLE = ['login', 'name', 'email'];

    private const array STATUSES = ['all', 'banned', 'suspicious', 'online'];

    private const string DEFAULT_SORT = 'created_at';

    public function index(Request $request): Response
    {
        $roles = Role::query()->orderBy('level')->get();

        $role = $request->query('role');
        $role = is_string($role) && $roles->contains('name', $role) ? $role : 'all';

        $status = $request->query('status');
        $status = is_string($status) && in_array($status, self::STATUSES, true) ? $status : 'all';

        $sort = $request->query('sort');
        $sort = is_string($sort) && in_array($sort, self::SORTABLE, true) ? $sort : self::DEFAULT_SORT;

        $direction = $request->query('direction');
        $direction = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : 'desc';

        $q = $request->query('q');

        $query = User::query()->with('roles')->withActiveBanFlag();

        if ($role !== 'all') {
            $query->whereHas('roles', fn (Builder $r) => $r->where('name', $role));
        }

        match ($status) {
            'banned' => $query->whereHas('bans', fn (Builder $b) => $b->active()),
            'suspicious' => $query->where('is_suspicious', true),
            'online' => $query->online(),
            default => null,
        };

        TableQuery::apply($query, $request, self::SORTABLE, self::DEFAULT_SORT, self::SEARCHABLE);
        // Стабильный порядок при равных значениях сортируемой колонки.
        $query->orderBy('id', $direction);

        return Inertia::render('core::admin/users/index', [
            'users' => UserListResource::collection(TableQuery::paginate($query, $request)),
            'filters' => [
                'q' => is_string($q) ? $q : '',
                'role' => $role,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'roles' => RoleResource::collection($roles)->resolve($request),
        ]);
    }
}
