<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Ladcms\Core\Admin\TableQuery;
use Ladcms\Core\Http\Resources\Admin\BanResource;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;

class BansController
{
    private const array SORTABLE = ['starts_at', 'expires_at'];

    private const array STATUSES = ['active', 'expired', 'all'];

    private const string DEFAULT_SORT = 'starts_at';

    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, self::STATUSES, true) ? $status : 'active';

        $sort = $request->query('sort');
        $sort = is_string($sort) && in_array($sort, self::SORTABLE, true) ? $sort : self::DEFAULT_SORT;

        $direction = $request->query('direction');
        $direction = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : 'desc';

        $query = Ban::query()->with(['user', 'bannedBy']);

        match ($status) {
            'active' => $query->active(),
            // «Истёкший» = неактивный, в том числе снятый досрочно.
            'expired' => $query->whereNot(fn ($q) => $q->active()),
            default => null,
        };

        TableQuery::apply($query, $request, self::SORTABLE, self::DEFAULT_SORT);
        $query->orderBy('id', $direction);

        return Inertia::render('core::admin/moderation/bans', [
            'bans' => BanResource::collection(TableQuery::paginate($query, $request)),
            'filters' => ['status' => $status, 'sort' => $sort, 'direction' => $direction],
            'can' => ['lift' => $user->can('users.ban')],
        ]);
    }
}
