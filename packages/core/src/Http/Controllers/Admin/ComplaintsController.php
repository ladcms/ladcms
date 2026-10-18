<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Controllers\Admin;

use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Ladcms\Core\Actions\Moderation\ReviewComplaint;
use Ladcms\Core\Admin\TableQuery;
use Ladcms\Core\Enums\ComplaintStatus;
use Ladcms\Core\Http\Requests\Admin\ReviewComplaintRequest;
use Ladcms\Core\Http\Resources\Admin\ComplaintResource;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\User;

class ComplaintsController
{
    private const array SORTABLE = ['created_at', 'status'];

    private const string DEFAULT_SORT = 'created_at';

    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $statuses = [...array_column(ComplaintStatus::cases(), 'value'), 'all'];

        $status = $request->query('status');
        $status = is_string($status) && in_array($status, $statuses, true) ? $status : ComplaintStatus::Pending->value;

        $sort = $request->query('sort');
        $sort = is_string($sort) && in_array($sort, self::SORTABLE, true) ? $sort : self::DEFAULT_SORT;

        $direction = $request->query('direction');
        $direction = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : 'desc';

        $query = Complaint::query()->with(['author', 'targetUser', 'reviewer']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        TableQuery::apply($query, $request, self::SORTABLE, self::DEFAULT_SORT);
        $query->orderBy('id', $direction);

        return Inertia::render('core::admin/moderation/complaints', [
            'complaints' => ComplaintResource::collection(TableQuery::paginate($query, $request)),
            'filters' => ['status' => $status, 'sort' => $sort, 'direction' => $direction],
            'statuses' => $statuses,
            'can' => ['review' => $user->can('complaints.review')],
        ]);
    }

    public function review(ReviewComplaintRequest $request, #[CurrentUser] User $actor, Complaint $complaint, ReviewComplaint $action): RedirectResponse
    {
        try {
            $action->handle($actor, $complaint, $request->decision());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core::admin.saved')]);

        return back();
    }
}
